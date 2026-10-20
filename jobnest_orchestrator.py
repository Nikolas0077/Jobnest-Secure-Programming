"""JobNest local orchestrator: Codex plans/reviews, Antigravity implements.

Usage:
  py -3 jobnest_orchestrator.py --task "Implement login"
  py -3 jobnest_orchestrator.py --watch

For --watch, write a UTF-8 .md task to .orchestration/inbox/ (e.g. via Codex Desktop).
Only use on a disposable feature branch, after inspecting the repo and CLI permissions.
"""
import argparse
import json
import shutil
import subprocess
import sys
import tempfile
import time
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parent
STATE = ROOT / '.orchestration'
INBOX = STATE / 'inbox'
MAX_REVISIONS = 2


def cmd(exe, *args, stdin=None, timeout=1200):
    """Run one installed CLI in the repo and return its captured output; raise on failure."""
    binary = shutil.which(exe)
    if not binary:
        raise RuntimeError(f'{exe} not found in PATH. Open a new terminal and retry.')
    # CLI grandchildren can inherit pipes on Windows and keep communicate() waiting after exit.
    with tempfile.TemporaryFile(mode='w+', encoding='utf-8') as out, \
         tempfile.TemporaryFile(mode='w+', encoding='utf-8') as err:
        p = subprocess.run([binary, *args], cwd=ROOT, input=stdin,
                           text=True, encoding='utf-8', errors='replace',
                           stdout=out, stderr=err, timeout=timeout, check=False)
        out.seek(0)
        err.seek(0)
        p.stdout, p.stderr = out.read(), err.read()
    if p.returncode:
        raise RuntimeError(f'{exe} exited {p.returncode}: {p.stderr[-2500:]}')
    return p


def ensure_safe_repo():
    """Require a feature branch with no unrelated pending edits before agent work."""
    branch = cmd('git', 'branch', '--show-current').stdout.strip()
    if not branch or branch in {'main', 'master'}:
        raise RuntimeError('Use a named feature branch, not main/master or detached HEAD.')
    changed = cmd('git', 'status', '--porcelain', '--untracked-files=normal').stdout
    unapproved = [line for line in changed.splitlines()
                  if not line[3:].replace('\\', '/').startswith('.orchestration/')
                  and line[3:] != 'jobnest_orchestrator.py']
    if unapproved:
        raise RuntimeError('Commit/stash existing changes first:\n' + '\n'.join(unapproved[:20]))
    return branch


def codex(prompt, outfile):
    """Ask Codex for a read-only plan/review and save its text to outfile."""
    p = cmd('codex', 'exec', '--sandbox', 'read-only',
            '--output-last-message', str(outfile), '-', stdin=prompt)
    if not outfile.exists():
        raise RuntimeError('Codex did not write an output file: ' + p.stderr[-1500:])
    return outfile.read_text(encoding='utf-8')


def gemini(prompt, outfile):
    """Ask Antigravity to implement a brief and save its JSON response."""
    # shortcut: Antigravity auto-approves tools inside its sandbox; use scoped allow-rules before shared-branch runs.
    p = cmd('agy', '-p', prompt, '--output-format', 'json', '--print-timeout', '15m',
            '--sandbox', '--dangerously-skip-permissions', timeout=1000)
    try:
        obj = json.loads(p.stdout)
    except json.JSONDecodeError as exc:
        raise RuntimeError('Antigravity returned invalid JSON: ' + p.stdout[-1500:]) from exc
    outfile.write_text(json.dumps(obj, indent=2, ensure_ascii=False), encoding='utf-8')
    if obj.get('status') != 'SUCCESS':
        raise RuntimeError('Antigravity status: ' + str(obj.get('status')) + ': ' + str(obj.get('error')))
    if obj.get('denied_actions'):
        raise RuntimeError('Antigravity actions denied: ' + str(obj['denied_actions']))
    return obj.get('response', '')


def run(task):
    """Plan, implement, and review one task; leave commit and merge to a human."""
    branch = ensure_safe_repo()
    stamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    folder = STATE / 'runs' / stamp
    folder.mkdir(parents=True, exist_ok=False)
    (folder / 'request.md').write_text(task, encoding='utf-8')
    print(f'[{stamp}] branch={branch} | planning... ', flush=True)
    context = ('Project: JobNest, PHP Native, MySQL, no Laravel/Blade. '
               'You are a read-only Tech Lead. Respect existing docs/api-contract.md when available. '
               'Do not edit files. Output a precise implementation brief, files to touch, '
               'security constraints, acceptance criteria, and test instructions.\n\nTASK:\n' + task)
    plan = codex(context, folder / 'plan.md')
    review = ''
    for attempt in range(MAX_REVISIONS + 1):
        print(f'  Gemini implementation pass {attempt + 1}...', flush=True)
        instruction = ('You are the coding agent for JobNest. Work ONLY in this repository. '
                       'Follow the Tech Lead brief; implement and test where permissions allow. '
                       'Never modify docs/api-contract.md or .env; do not commit, push, or merge. '
                       'If shell permissions prevent testing, report it explicitly.\n\n'
                       f'BRIEF:\n{plan}\n\nREVIEW FEEDBACK:\n{review or "Initial implementation."}')
        gemini(instruction, folder / f'gemini_{attempt}.json')
        print('  Codex reviewing Git diff...', flush=True)
        review_prompt = ('You are an independent read-only reviewer of the current Git working tree. '
                         'Read git diff plus untracked source files, compare to plan, and assess '
                         'correctness, security, tests, and remaining requirements. '
                         'Do not modify files. Begin your response with exactly APPROVED or CHANGES_REQUIRED '
                         'on the first line. Do not approve merely because the agent claims success. '
                         'Mention blocked tests.\n\nPLAN:\n' + plan)
        review = codex(review_prompt, folder / f'review_{attempt}.md')
        if review.lstrip().startswith('APPROVED'):
            print('  Codex review: APPROVED (human review still required).', flush=True)
            break
        print('  Codex review: changes required.', flush=True)
    (folder / 'final_status.txt').write_text(review, encoding='utf-8')
    print(f'Finished. Inspect the files in Antigravity IDE, and review {folder}')
    print('No automatic commit/push/merge was performed.')


def watch():
    """Process Markdown tasks dropped into .orchestration/inbox/ continuously."""
    INBOX.mkdir(parents=True, exist_ok=True)
    print(f'Watching {INBOX} for *.md tasks. Ctrl+C to stop.', flush=True)
    while True:
        for file in sorted(INBOX.glob('*.md')):
            claimed = file.with_suffix('.running')
            try:
                file.rename(claimed)
                run(claimed.read_text(encoding='utf-8'))
                claimed.rename(file.with_suffix('.done'))
            except Exception as exc:
                print(f'ERROR: {exc}', file=sys.stderr, flush=True)
                if claimed.exists():
                    claimed.rename(file.with_suffix('.failed'))
        time.sleep(3)


def main():
    """Parse --task or --watch and report orchestration failures."""
    parser = argparse.ArgumentParser(description='JobNest Codex → Gemini orchestrator')
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument('--task', help='One task to implement')
    group.add_argument('--watch', action='store_true', help='Watch inbox for Codex Desktop task files')
    args = parser.parse_args()
    try:
        watch() if args.watch else run(args.task)
    except KeyboardInterrupt:
        print('\nStopped.')
    except Exception as exc:
        print('ERROR:', exc, file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
