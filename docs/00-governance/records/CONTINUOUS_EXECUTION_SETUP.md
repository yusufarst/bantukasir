# Continuous execution setup (D-101)

| | |
| --- | --- |
| Status | RECORD ([SOURCE_OF_TRUTH](../SOURCE_OF_TRUTH.md)) |
| Decision | [D-101](../DECISIONS.md), continuous Task execution; [EXECUTION_CONTEXT](../../11-tasks/EXECUTION_CONTEXT.md) section 19, CE-01 to CE-82; FD-10 |
| Written | 23 October 2026 (Asia/Jakarta) by the implementation executor, under the Owner's instruction "Continuous execution setup (D-101)", revision 2 (final) |
| Branch | `ops/continuous-execution`, created from `develop` at `7fed319ce1010c39a86e85d6cb88ec35e6c19db6` (the D-100 bookkeeping commit), carrying the twelve document changes of the D-101 amendment |
| Session | Claude Opus 5.5 (`claude-opus-5-5`), effort XHigh as the instruction names it; a new local Desktop session in the main working tree, no worktree; permission mode Auto by the Owner's decision in the session (section 3.1). Continued from item 5.4 of the instruction in a second local Desktop session in the same working tree (created 23 October 2026, 19:47), Claude Opus 5.5, effort XHigh, permission mode Auto, on the Owner's instruction "Lanjutkan setup D-101 dari checkpoint §5.4" |

## 1. Files added

| File | What it is |
| --- | --- |
| `scripts/executor/lib.sh` | The shared library: constants, the executor state, the lock and its liveness check, the invocation identity, the message and path rules, the GitHub calls and process queries (fixture-replaceable), the parsers of TASK_PLAN section 4 and of the effort table |
| `scripts/executor/state.sh` | `show`, `heartbeat`, `phase`, `stop`, `resume`, `unlock`, `adopt`, `probe-holder`, `probe-invocation` |
| `scripts/executor/tick.sh` | The dispatcher's only command (CE-03); `--dry-run`; `--explain TASK-NNN [--worktree]` |
| `scripts/executor/snapshot.sh` | A tree object of the working tree, built in a temporary index |
| `scripts/executor/review-pack.sh` | The review package of CE-40 and its `--check` |
| `scripts/executor/guard.sh` | The publication guard of CE-31; `--preview`, `--accept <sha256>` |
| `scripts/executor/commit.sh` | The only way the executor commits (CE-30); `--probe` |
| `scripts/executor/push.sh` | The only way the executor pushes (CE-30, CE-31); `--probe` |
| `scripts/executor/pr.sh` | `open`, `body`, `wait-ci`, `rerun`, `status`, `comments`, `api` |
| `scripts/executor/selftest.sh` | The sandboxed self-test, cases 1 to 14; `--host`, the host probes (a) to (e) |
| `.claude/settings.json` | The project's permission rules and settings of section 4.12 of the instruction |
| `.claude/agents/bk-executor-medium.md`, `bk-executor-high.md`, `bk-executor-xhigh.md`, `bk-executor-max.md` | The executor agents, Claude Opus 5.5 at the effort each name says |
| `.claude/agents/bk-reviewer-high.md`, `bk-reviewer-xhigh.md`, `bk-reviewer-max.md` | The isolated read-only reviewer agents: tools Read, Grep and Glob only |
| `docs/00-governance/records/CONTINUOUS_EXECUTION_SETUP.md` | This record |

Every script is bash for Git for Windows (`#!/usr/bin/env bash`, `set -euo pipefail`, LF), uses nothing beyond bash, coreutils, `sed`, `awk`, `grep`, `git`, `gh`, Docker and, on Windows, Windows PowerShell for process information, and names its CE rules, usage and exit codes in its header. Only `commit.sh`, `push.sh` and `pr.sh open|body|rerun` write to Git or GitHub.

## 2. How it works

### 2.1 The state (CE-20, CE-21)

The state is `bantukasir-executor/` in the Git common directory: `state` (Git configuration format, written only with `git config -f`), `log` (one line per event, Asia/Jakarta time), `lock/`, the change requests and ignored comments waiting for the next delegation (`requests`, `ignored`), the guard's stamp and candidates, and the review packages (`review/<task>/r<n>/`). The keys are those of section 4.1 of the instruction, with these additions: `task.title`, `task.contract`, `task.effortSource`, `owner.effortSource`, `owner.lastResumeCommentId`, `merge.pendingSha`, `merge.acceptedByOwner`, `merge.acceptedSha` (section 8), `ci.head`, `ci.failedHead`. Free text — `loop.stopDetail` and `owner.note` — is stored as `b64:<base64>`, so that no value can be read as an option of `git config`; `state.sh show` decodes it.

### 2.2 The lock and its liveness check (CE-22, CE-24)

The lock is the directory `lock/`, created with `mkdir`; it holds `token`, `since`, `heartbeat`, `boot` and `holder`. Every acquisition — by the tick, by `resume --continue` or by a takeover — writes all five.

- `holder_record` lists the ancestors of the script that are not shells (`SHELL_NAMES`), up to the first system process (`SYSTEM_NAMES`; PID 1 on Linux) or a missing parent, each as `pid|creation time|name`. On Windows it follows the bash runtime's own `/proc` chain first, which an `exec` does not break, then the Windows parents from one `Get-CimInstance Win32_Process` call; a parent created after its child is a reused PID and ends the walk.
- `holder_state` gives `INACTIVE BOOT` when the computer started after the lock was taken; `ACTIVE <pid>` when a recorded process still exists with the same PID and creation time; `INACTIVE PROCESSES_GONE` when none does; `UNKNOWN <reason>` when there is no record or the process list or boot time cannot be read.
- Only the two `INACTIVE` results allow a takeover, which logs the evidence and the old holder. With `ACTIVE` or `UNKNOWN` the tick writes nothing: `NOOP LOCKED` while the heartbeat is younger than 120 minutes, then `STOPPED LOCK_UNVERIFIED`. The age never leads to a takeover.

### 2.3 The tick (CE-03, CE-10 to CE-13, CE-61, CE-62, CE-70 to CE-72)

`tick.sh` follows the order of section 4.3 of the instruction: effort overrides; `.git/index.lock` (third run a stop); `git fetch` and the open pull requests (a failure is `NOOP GITHUB_UNAVAILABLE`, the third in a row a stop); without `loop.phase`, a reconstruction from exactly one open loop pull request whose head equals the local branch, else `STOPPED NOT_INITIALIZED`; the lock; then by phase. Its first line is always `NOOP`, `STOPPED` or `RUN`; it always exits 0, and an internal error prints `STOPPED TICK_ERROR <step>`. `--dry-run` works on a copy of the state, logs nothing, takes no lock and does not move local `develop`.

After a merge it checks, in order: the base; the merge commit on `origin/develop`; the content (the same tree when the merge's first parent is an ancestor of the head, otherwise the same `git patch-id --stable`); the author's e-mail; local `develop` (`git fetch origin develop:develop`, or `git merge --ff-only` when `develop` is checked out); CI on `develop` at the merge. It records `merge.*` and selects the next Task, or stops for the Owner's `/stop`.

### 2.4 Commits and pushes, kept apart (CE-30, CE-31)

- `commit.sh` and `push.sh` are `ask` rules, so each prompts the Owner on its own.
- `invocation_id` identifies the Bash call that runs a script: the outermost shell found by walking up from `$PPID` while the process is a shell, by PID and creation time; the script itself when its parent is not a shell. `push.sh` refuses `SAME_INVOCATION` when it equals `commit.lastInvocation`, and `commit.sh` when it equals `push.lastInvocation`.
- `push.sh` refuses `UNAPPROVED_COMMIT` for any commit of `HEAD --not --remotes=origin` that is not in `commit.approved`, and requires the guard's stamp on HEAD and a fast-forward.

### 2.5 The reviewers (CE-40, CE-11)

The three reviewer agents have `tools: Read, Grep, Glob` and `disallowedTools: Bash, Edit, Write, NotebookEdit, Agent`. They read the package that `review-pack.sh` writes in the state, and `--check` proves afterwards that the tree, HEAD, the index and the state are unchanged. The executor and the reviewers run Claude Opus 5.5 at the effort their definitions name, as the table of EXECUTION_CONTEXT section 19 assigns them.

## 3. Readings of the instruction

1. **Permission mode.** The instruction names Accept edits. The session was in Auto mode; asked to switch back, the Owner decided, verbatim: "OWNER DECISION: Saya memilih mode AUTO untuk Claude Code Desktop. Jangan meminta saya kembali ke Accept edits. Lanjutkan setup D-101 menggunakan Auto selama pengamanan tetap berlaku. Commit dan push tetap wajib meminta persetujuan saya secara terpisah. Merge tetap hanya dilakukan Owner." The official documentation (permission modes, "How the classifier evaluates actions") states that in Auto mode actions matching allow, ask or deny rules resolve first, that explicit ask rules still force a prompt, and that deny rules block in every mode; everything else goes to the classifier instead of a prompt, and writes to protected paths such as `.claude/` go to the classifier. So the ask rules of commit, push, resume, unlock, adopt, `rm`, `mv` and the two `Edit` rules prompt in both modes, and the deny rules refuse in both; what differs is that a command matching no rule is reviewed by the classifier rather than by the Owner. The probes of section 5 were run in Auto mode.
2. **No state.** "No `loop.phase`" is the test for a missing state, in `tick.sh` step 4 and in `state.sh adopt`: during the setup, `pr.sh open` and `pr.sh wait-ci` add `pr.*` and `ci.*` keys to the state that `commit.sh` and `push.sh` created.
3. **Stops before the lock.** A stop found in steps 1 to 3 of the tick is recorded in the state only when no other session holds the lock; otherwise it is printed and left to that session, which may still be working.
4. **Change requests on an `ops/` pull request** give `STOPPED OPS_CHANGE_REQUEST`: an `ops/` change needs its own Owner instruction (CE-82), and the executor agents never change `scripts/executor/` or `.claude/`.
5. **A merge of `develop` in progress** (EXC-36): `commit.sh` accepts the merge's own staged changes when `MERGE_HEAD` exists, requires that no path is unmerged and that every path given is staged, and completes the merge.
6. **Stop reasons the instruction does not name:** `MERGE_NOT_ON_DEVELOP` (check 2 of CE-61), `STATE_INVALID` (a phase outside the set), `OPS_CHANGE_REQUEST`; `PR_HEAD_MOVED` also when a pull request was merged with a head other than the recorded one.
7. **Comments.** `/resume` on a pull request in `AWAIT_MERGE` is a command, not a change request; a `/resume` is acted on once (`owner.lastResumeCommentId`). The Owner's resume also sets `task.resumes` back to 0.
8. **CI failures** are counted once per head commit (`ci.failedHead`), so that waiting again on the same red head does not count twice.
9. **The self-test** runs every script in its sandbox through `xargs`, a process that is not a shell, as separate Bash calls of Claude Code are; the `SAME_INVOCATION` case runs `bash -c "bash commit.sh …; bash push.sh"` directly. Case 14 checks the two `Edit` ask rules, which section 4 of the instruction adds after the self-test: before they were added, that one case failed by construction.
10. **The reviewer probe package is round 1.** The round-0 package of the first session (section 5.6) was kept, as the Owner instructed, and `review-pack.sh` never overwrites a package. Its `--check` now gives `CHANGED tree`, because this record changed after it was written, so it cannot prove the reviewers left the tree unchanged. A new package, `--round 1`, was written for the probes.
11. **`SubagentHandback`.** Each reviewer listed Read, Grep and Glob, and also `SubagentHandback`, a tool the Claude Code runtime gives every subagent to return its report to the session that invoked it. It neither runs a command nor writes a file. The instruction expects Read, Grep and Glob only; the difference was reported to the Owner, who directed that the setup continue.

## 4. The scheduled task

The Owner creates it (D-101; the instruction, section 9). Its settings:

- Claude Desktop, Code tab → Scheduled → New task → Set up manually; name `bantukasir-executor`.
- In the instructions box: permission mode **Accept edits**; model **Claude Opus 5.5**.
- Advanced settings → General: **Only on this computer** on; Local folder `C:\Projects\bantukasir`.
- Trigger: **Hourly**. Create; trust the folder if asked.
- On its detail page, Edit: **Worktree** off; Status **Active**.
- Recommended: Settings → This computer → System → **Keep computer awake**.

The dispatcher's instructions, verbatim:

```text
BantuKasir continuous execution, dispatcher (D-101; EXECUTION_CONTEXT section 19, CE-03).
1. In this folder run exactly: bash scripts/executor/tick.sh
2. If the first line of its output begins with NOOP or STOPPED, reply with that line only and end the run. Read no file and run nothing else.
3. If it begins with RUN, invoke the subagent named by agent= on that line, in the foreground, giving it, verbatim, all the output after that first line as its task. Wait until it finishes, then reply with its report and end the run.
4. If the output begins with anything else, reply with its first 20 lines and end the run.
5. Do nothing else in this run.
```

## 5. Verification

Status on 23 October 2026, 20:18 (Asia/Jakarta): **complete.** The first session stopped at item 5.4 of the instruction, because the reviewer agents could not be invoked in the session that wrote them (section 5.6 below). The second session ran the self-test again, the reviewer probes and the permission probes; the host probes and the read-only checks of sections 5.4 and 5.5 are the first session's.

### 5.1 Environment (preflight, 17:57)

Git for Windows 2.55.0.windows.3; GNU bash 5.3.15; `gh` 2.96.0, logged in as `yusufarst` with the scopes `gist`, `read:org`, `repo`, `workflow`; Docker client and server 29.8.0; Windows PowerShell 5.1.26100.9711; Gitleaks v8.30.1 in `bantukasir-app:local`. Repository settings read: `allow_squash_merge` true, `allow_merge_commit` true, `allow_rebase_merge` true, `squash_merge_commit_title` `COMMIT_OR_PR_TITLE`, `squash_merge_commit_message` `COMMIT_MESSAGES`, `delete_branch_on_merge` false, default branch `main`. Every check of the instruction's section 2 passed: `develop` at `7fed319` locally and on GitHub, no open pull request into `develop`, the twelve documents with their hashes, the known untracked files only (`.claude/settings.local.json` ignored by the user-global rule), the worktree `bantukasir-task002` at `936be82`, `stash@{0}` only, no executor state, `.claude/agents/` empty and older than the session, no ignore rule on the new paths, the Owner's identity, no effort override.

### 5.2 Documentation consulted (EXC-03)

The official Claude Code documentation, read on 23 October 2026: [permissions](https://code.claude.com/docs/en/permissions) (rule syntax; the order deny, ask, allow; compound commands and wrappers; what a Bash rule does not match; Read and Edit rules, and that `Write(path)`, `NotebookEdit(path)` and `Glob(path)` rules never match; PowerShell rules; a bare tool name in deny removes the tool); [permission modes](https://code.claude.com/docs/en/permission-modes) (`acceptEdits`; Auto mode and "How the classifier evaluates actions"; protected paths); [all settings](https://code.claude.com/docs/en/settings-reference) (`attribution`, `includeGitInstructions`, `env`, `permissions.disableBypassPermissionsMode`, `maxEffortLevel`); [subagents](https://code.claude.com/docs/en/sub-agents) (`model` with a full model ID, `effort`, `tools` as an allowlist, `disallowedTools`, the watch of `.claude/agents/` limited to directories that existed when the session started); [Desktop scheduled tasks](https://code.claude.com/docs/en/desktop-scheduled-tasks). Every key of sections 4.11 and 4.12 of the instruction exists as written.

### 5.3 Self-test (`bash scripts/executor/selftest.sh`)

- Third run, before the two `Edit` ask rules were added: 155 cases PASS, 1 FAIL — "the two Edit ask entries are missing", by construction of the build order (section 3, reading 9); 504 s.
- Final run, after the two `Edit` rules and the snapshot fix below: exit 0, `SUMMARY 156 passed, 0 failed`, 605 s. Its log is not clean evidence: an earlier run, stopped from the session after the snapshot defect was found, kept running and wrote to the same log file.
- Clean run, second session, 19:48: one run into a new log, exit 0, `SUMMARY 156 passed, 0 failed`, 531 s.
- Defects found and fixed during the build: `snapshot.sh` built its temporary index from empty, so executable files lost their mode in the snapshot tree (fixed: the temporary index starts as a copy of the real one; the sandbox case 11 now holds an executable file); `holder_state` skipped a holder record without a final newline; the self-test reported nothing for a case whose precondition failed (each such case now reports FAIL).

### 5.4 Host probes (`bash scripts/executor/selftest.sh --host`): all PASS

| Probe | Result |
| --- | --- |
| (a) holder record of the session | `claude.exe` (the session, created 10:41:07Z), `claude.exe` (the Desktop application, 09:24:41Z), `sihost.exe` (07:20:12Z); three processes neither shell nor system |
| (b) a background `ping -n 120 127.0.0.1` | `ACTIVE 20288` while it ran; after `taskkill //PID 20288 //F`, `INACTIVE PROCESSES_GONE` |
| (c) that PID with another creation time | `INACTIVE PROCESSES_GONE` |
| (d) boot time | 1792739995 (23 October 2026, 14:19:55 Asia/Jakarta), earlier than now |
| (e) an empty record | `UNKNOWN NO_RECORD` |

The invocation check: two separate Bash calls of `bash scripts/executor/state.sh probe-invocation` gave `14416|2026-10-23T11:45:03.7494080Z` and `23792|2026-10-23T11:45:07.3048000Z`, two identities; one call with both commands joined by `;` gave `30580|2026-10-23T11:45:12.5237550Z` twice, one identity.

### 5.5 Read-only checks on the real repository

- `bash scripts/executor/tick.sh --dry-run`: `STOPPED NOT_INITIALIZED no state and 0 open loop pull requests`.
- `bash scripts/executor/tick.sh --explain <Task> --worktree`: TASK-004 `READY`, eligible, `bk-executor-xhigh` (xhigh), `bk-reviewer-xhigh` (xhigh), `bk-reviewer-high` (high); TASK-005 `READY`, eligible, `bk-executor-high`, `bk-reviewer-high`, `bk-reviewer-high`; TASK-021 `bk-executor-max`, `bk-reviewer-max`, `bk-reviewer-xhigh`; TASK-044 `bk-executor-high`, `bk-reviewer-high`, `bk-reviewer-high`; TASK-047 `bk-executor-medium`, `bk-reviewer-high`, `bk-reviewer-high`. TASK-021, TASK-044 and TASK-047 are `TODO` and not eligible, with their unmet dependencies and gates listed.
- `bash scripts/executor/state.sh probe-holder`: the record of 5.4 (a), `HOLDER_STATE ACTIVE 6408`.
- `bash scripts/executor/snapshot.sh --base origin/develop`: tree `d247318772db9f8c0db0a49c389664455f83cf41`, 31 files changed; the hash of `git ls-files -s` (`d70159d1…3825c4b`) is the same before and after.

### 5.6 Reviewer probes: PASS (second session)

In the second session the three reviewer agents were available. The probe package `.git/bantukasir-executor/review/ops-continuous-execution/r1/` (section 3, reading 10) holds tree `fceb71caf327c8bf6c33c97a3d888e265dc4a273`, HEAD `7fed319ce1010c39a86e85d6cb88ec35e6c19db6`, index hash `d70159d1ad0668826ffa3884695c9b58ebf1f9a635ab69602910228eb3825c4b` and state `none`. `bk-reviewer-high`, `bk-reviewer-xhigh` and `bk-reviewer-max` were invoked one after the other with the probe task of the instruction.

| Agent | Tools it listed | Manifest's first line | `git status` | `probe-written.txt` |
| --- | --- | --- | --- | --- |
| `bk-reviewer-high` | Read, Grep, Glob, `SubagentHandback` | quoted exactly | not run: no tool to run a command | not created: no tool to write a file |
| `bk-reviewer-xhigh` | Read, Grep, Glob, `SubagentHandback` | quoted exactly | not run: no tool to run a command | not created: no tool to write a file |
| `bk-reviewer-max` | Read, Grep, Glob, `SubagentHandback` | quoted exactly | not run: no tool to run a command | not created: no tool to write a file |

Afterwards `<package>/probe-written.txt` did not exist, and `bash scripts/executor/review-pack.sh --check <package>` printed `UNCHANGED`. `SubagentHandback` is the runtime's hand-back of the report (section 3, reading 11).

First session: the probe package was created: `bash scripts/executor/review-pack.sh --base origin/develop --round 0` wrote `.git/bantukasir-executor/review/ops-continuous-execution/r0/`, with tree `d247318772db9f8c0db0a49c389664455f83cf41`, HEAD `7fed319ce1010c39a86e85d6cb88ec35e6c19db6`, index hash `d70159d1ad0668826ffa3884695c9b58ebf1f9a635ab69602910228eb3825c4b` and state `none`. Invoking `bk-reviewer-high` was refused: "Agent type 'bk-reviewer-high' not found", the session knowing only the built-in agents. The Desktop session that wrote the agent files did not load them, although `.claude/agents/` existed, empty, before the session started (the directory was created at 17:25 and the session at 17:41). `bk-reviewer-xhigh` and `bk-reviewer-max` were therefore not invoked. The Owner decided to stop here; the probes run in a new session, which loads the agents when it starts.

### 5.7 Permission probes: PASS, 84 of 84 (second session, Auto mode)

Each probe ran as its own call, exactly as the instruction writes it, from the root of the working tree.

| Probes | Expected | Result |
| --- | --- | --- |
| P01 to P69 | D | each refused by a rule without a prompt: "Permission to use Bash with command … has been denied." For P14 the refusal named the `xargs git push --dry-run origin` part of the pipeline |
| P70 | D | the PowerShell tool is absent from the session: it is not among the session's tools, and a search of the deferred tools finds none |
| A1 `commit.sh --probe`, A2 `push.sh --probe`, A3 `state.sh unlock`, A4 `state.sh resume`, A6 `rm`, A7 `mv` | A | each prompted; the Owner, told beforehand that it was a probe, answered No, and nothing ran |
| A5 an Edit adding `# probe A5` to `scripts/executor/lib.sh` | A | prompted; No; afterwards `lib.sh` holds no such line |
| A8 a Write creating `scripts/executor/probe-write.txt` | A, through `Edit(scripts/executor/**)` | prompted; No; afterwards the file does not exist |
| S1 `tick.sh --dry-run` | S | ran: `STOPPED NOT_INITIALIZED no state and 0 open loop pull requests` |
| S2 `git status --short` | S | ran: the twelve modified documents and the untracked paths of section 5.1 |
| S3 `docker compose ps` | S | ran: the header only, no container running |
| S4 `pr.sh api repos/yusufarst/bantukasir --jq .default_branch` | S | ran: `main` |
| S5 `pr.sh api …/git/refs -f ref=refs/heads/bk-probe` | S | ran: `REFUSED ARGUMENT`, exit 1; no ref created |
| S6 `git merge --no-ff --no-commit origin/develop` | S | after `git merge-base --is-ancestor origin/develop HEAD` succeeded, ran: `Already up to date.` |

The separation of the two approvals rests on three things: the two `ask` rules A1 and A2, the `SAME_INVOCATION` refusal (self-test case 9 and the invocation check of 5.4), and the real commit and push of the instruction's section 6, made in separate calls at separate prompts.

### 5.8 Protected-path writes during the build

The nine writes under `.claude/` (the settings, the seven agents, the two `Edit` rules) were made in Auto mode, where the documentation routes protected-path writes to the classifier; the tool results do not show whether the Owner was prompted. After the two `Edit` rules were added, two edits under `scripts/executor/` (the snapshot fix and its self-test case) went through the `Edit(scripts/executor/**)` ask rule.

## 6. Known limits

- The permission rules are guard rails, not a sandbox. In Accept edits mode a command that matches no rule prompts the Owner; in Auto mode the classifier reviews it instead (section 3.1). Code run inside the containers is not covered by Bash rules: the identity check of the guard and the `UNAPPROVED_COMMIT` refusal of `push.sh` stop a commit made there.
- As the official documentation states, a Bash rule matches the command text that Claude writes, not the program: the same program called by its path (`/usr/bin/git push`), with a quoted subcommand (`git 'push'`) or from inside a script is not matched. The scripts' own checks and the guard complete the rules.
- Desktop may not notify the Owner of a permission prompt.
- **Run now** must not be used while a run is in progress.
- A lock whose holder cannot be shown to be inactive waits for the Owner. On this computer the holder record of a session includes the Desktop application and the Windows shell host (`sihost.exe`), which outlive a session: a lock left by a session that ended without releasing it reads `ACTIVE` until the computer restarts, so the loop gives `NOOP LOCKED` for two hours, then `STOPPED LOCK_UNVERIFIED`, and the Owner releases it with `bash scripts/executor/state.sh unlock` after checking that no session works on the Task.
- The guard's candidate extraction is heuristic, and the executor's judgment completes it.
- `psql`, a direct `gh api` or `docker run`, a plain `rm` or `mv`, and other commands outside the allow list prompt the Owner (or, in Auto mode, go to the classifier, except `rm` and `mv`, which are `ask` rules); `pr.sh api` is the unprompted, GET-only read of the GitHub API.
- The Linux paths of the process functions (`/proc`) are written for a Linux run of the self-test but were not exercised: the self-test ran on the Owner's Windows computer.

## 7. Merge

Recorded by the next Task (EXECUTION_CONTEXT CE-50).

## 8. Remediation of the review of pull request #4

On 23 October 2026 the automated review of pull request #4 at `3ce2893` raised two findings, which the Owner verified. By his instruction "Remediasi terbatas PR #4 — D-101", only those two were fixed, in the second session, and every edit under `scripts/executor/` was approved at its own prompt.

| Finding | Defect | Fix |
| --- | --- | --- |
| P1, `state.sh` | After `STOPPED MERGE_AUTHOR` or `STOPPED MERGE_CONTENT`, `resume --accept-merge <sha>` recorded the merge and set `SELECT`, so the checks after that predicate (local `develop`, green CI on `develop` at the merge) were skipped and the next tick could start a Task on an unverified base | `resume --accept-merge` records `merge.acceptedSha` and adds the stop's reason to `merge.acceptedByOwner` (both reasons when a merge needs both), then restores `AWAIT_MERGE`. In `verify_merge`, `merge_accepted` waives only the accepted predicate for that merge commit; every other check runs as before, and the merge is recorded only after CI on `develop` passes, when both keys are cleared and the log names the acceptance. `--continue` with `--accept-merge` is refused, because the tick, not a session holding the lock, completes the verification |
| P2, `pr.sh` | `open`, when the branch's pull request was already open, printed it and exited without recording `pr.number` and `pr.head`; after an interruption between the creation and the record, `AWAIT_MERGE` would stop with `STATE_INVALID` | The already-open path checks the pull request's number and branch, refuses `HEAD` when its head is not the local HEAD, and records `pr.number` and `pr.head` when a state exists, as the creation path does |

This changes the instruction's section 4.2, under which `--accept-merge` set `SELECT`, by the Owner's remediation instruction.

Regression tests in `selftest.sh`:

- Case 3, the MERGE_AUTHOR exception: `--continue` is refused; after the acceptance the phase is `AWAIT_MERGE` and no merge is recorded; the next tick gives `NOOP DEVELOP_CI_PENDING`; after CI passes, `RUN start`, the merge recorded, local `develop` fast-forwarded and the acceptance cleared.
- Case 3, the MERGE_CONTENT exception: after the acceptance a diverged local `develop` gives `STOPPED DEVELOP_DIVERGED`; once it is restored and the loop resumed, a red CI on `develop` gives `STOPPED DEVELOP_CI_RED`.
- Case 12: an open pull request whose head is not the local HEAD gives `REFUSED HEAD` and records nothing; with the right head, `PR 7 … (already open)` and `pr.number` and `pr.head` recorded.

The previous test of case 3, which expected `SELECT` after the acceptance, was replaced. The self-test after the fixes: exit 0, `SUMMARY 164 passed, 0 failed`, 626 s. Read against the code at `3ce2893`, which recorded the merge at the acceptance and never refused `--continue`, and whose `pr.sh open` neither refused a different head nor recorded an open pull request, the assertions on what follows an acceptance and on an already open pull request fail there; the self-test was not run against that code. The permission rules did not change, so the 84 permission probes were not repeated.
