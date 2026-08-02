# CLAUDE.md — Project Instructions

> This file is read automatically by Claude Code at the start of every session.
> Do not skip it. Do not re-scan the full codebase before reading it.

## 0. Scope Boundary — HARD LIMIT, NEVER CROSS

> This project may live inside a larger repo. Claude is only allowed to
> work inside the folder(s) listed below — **folders only, never
> individual file exceptions.** This is not a soft preference — treat it
> like a sandbox wall.

**Allowed working folders:**
- `wp-content/plugins/wp-b2b-cutomer-products-addon` (this plugin folder, including all subfolders)

Rules:
- Everything Claude reads, writes, or reasons about must sit inside one
  of the folders listed above (including their subfolders). There are no
  single-file exceptions to this — if a file lives outside the listed
  folders, it is out of scope, period.
- Do NOT read, open, search, list, or reference any file outside these
  folders — including "just to check" or "for context." If something
  outside them seems relevant, **stop and ask the project owner** instead
  of reading it yourself.
- Do NOT write, edit, delete, move, or create files outside these
  folders, under any circumstance, even if a task seems to require it.
- Treat everything outside these folders as if it does not exist, unless
  the project owner explicitly pastes its content into the chat or
  explicitly adds a folder to the list above.
- If a task cannot be completed without going outside the boundary, say
  so clearly and stop — do not silently expand scope.
- `project.json` and `claude.json` in this folder should only ever
  describe files inside the allowed folders. Never add entries for
  files outside them.

## 1. Startup Protocol (do this first, every session)

1. Read `claude.json` and `project.json`.
2. **Check if they still contain placeholder/sample data** (e.g. paths
   like `"REPLACE_ME"`, example function names like `exampleFunction`,
   or a `last_full_resync` that looks like a template value).
   - If yes → this is the first real run. Do a **full checkup of the
     codebase, restricted strictly to the allowed folders in §0**, and
     populate both JSON files with real data (see §4).
   - If no → they reflect a real prior session. Do NOT re-scan the whole
     codebase. Trust these files as your cache.
3. Compare `## 2. My Requirements` in this file against the `milestones`
   array in `claude.json`:
   - If every requirement already has a corresponding milestone marked
     `"done"` → the project is caught up. Say so, and ask what to do next
     rather than redoing finished work.
   - If some requirements have no milestone, or have one that's
     `not_started` / `in_progress` / `blocked` → **continue from that
     point**. Do not restart or redo work already marked `done`.
4. Only open/read actual source files (within the allowed folders) if:
   - `project.json` doesn't have enough detail to complete the task, OR
   - You're about to edit that specific file, OR
   - `claude.json` flags that file under `"needs_resync"`.
5. If `claude.json` or `project.json` are missing entirely, or clearly
   contradict what you find in the code, say so before proceeding — don't
   silently guess or silently overwrite.

**Goal:** avoid full-codebase re-reads. Use the JSON files as a cache of
what you already learned. Only pay the cost of reading real source when
the cache doesn't answer the question, or on the first real setup pass.

## 2. My Requirements (project owner — edit this freely)

Im going to develop extenstion plugin. first setup this clearly as mentioned and then check security and issues. follow human maintainable code always. explain simply with comments

- requirement 01 - setup project.json and claode.json
- requirement 02 - securtiy check and human readable and maintainable code
- requirement 03 - set bounderies to this folder (wp-b2b-cutomer-products-addon)
- requirement 04 - implement WooCommerce registration extra fields (first name, last name, phone), a My Account phone field, and a Login/Register tabbed UI on the My Account page — each piece built as its own separately admin-toggleable feature, including separate enable switches for the tab UI's CSS and JS, matching the supplied procedural code but rebuilt as maintainable OOP classes with an admin settings page (no hardcoded on/off behavior).
- requirement 05 - (a) merge the plugin's admin pages under one common top-level wp-admin menu with the rest as submenus underneath it; (b) make the registration form's extra fields dynamic — admin can add/remove custom fields from the admin panel, not just toggle the 3 fixed ones; (c) expand "My Account phone field" into a full My Account page field-visibility control — show all account-details fields (first name, last name, display name, email, phone, password) with an individual show/hide switch per field.
- requirement 06 - add a "Customer type" field to the registration form (dropdown for the customer to pick their type). Admin picks which types are offered via a multi-select (fixed built-in catalog of types, not admin-editable text). Hard security requirement: the submitted value must be 100% restricted to the admin-approved type list — no injected/arbitrary values accepted anywhere in the save path — and this field must never be able to influence the new account's WordPress role/capabilities; admin account creation via this path must always be impossible.
- requirement 07 - (a) all Customer Type text (field label, placeholder, both error messages, and each type's displayed label) must be admin-customizable in the settings page, not hardcoded/relying on WordPress translation files — project owner needs German text and there's no .po/.mo translation shipped; (b) fix the frontend dropdown rendering "half cut"/unstyled; (c) fix the Login/Register tabs UI so that redisplaying the page after a validation error keeps the user on whichever form (Login or Register) they actually submitted, instead of always reverting to Login.
- requirement 08 - replace the fixed/invented Customer Type catalog with real WordPress roles (whatever currently exists on the site). Admin panel gets two multi-select dropdowns: Include and Exclude, both populated from real roles. On registration submit: if the selected role is in Exclude, the account is created as Subscriber; else if it's in Include, the account is created WITH that selected role; otherwise (blank/unrecognized/neither list) also Subscriber. 'administrator' must never be selectable or appliable under any configuration — this was never walked back from the original hard requirement, just re-scoped to "real roles" instead of "a fixed catalog".
- requirement 09 - (a) make First name / Last name / Phone field titles (labels) admin-customizable too, same as Customer Type's text; (b) replace the native `<select multiple>` for the Include/Exclude role pickers with a proper search + multi-select UI, since ctrl/cmd-click on a native multi-select is not discoverable and can't be done on touch devices — project owner reported the existing control "cannot correctly select".

## 3. New Requirement Workflow (when I give you a new requirement as a prompt)

When the project owner gives a new requirement in the chat/prompt (not by
editing this file directly), do the following, in order:

1. Add it to `## 2. My Requirements` above, worded clearly.
2. Add a corresponding entry to the `milestones` array in `claude.json`
   with status `not_started` (or `in_progress` if you're doing it in the
   same turn).
3. Do the actual work — **within the scope boundary in §0 only**.
4. Update `claude.json` (mark the milestone `done`, log the date, add a
   note of what actually changed) and `project.json` (if files/functions
   changed) before ending the turn.
5. Never treat a verbal/prompted requirement as "done" without writing it
   into both this file and `claude.json` — otherwise it will be forgotten
   next session.

## 4. Rules for Claude (must always follow)

- After completing any task, milestone, or meaningful change, **update
  `claude.json`**: mark the relevant milestone done, add a dated entry,
  note any new issues/blockers.
- If a task adds, removes, renames, or significantly changes a file or
  function, **update `project.json`** to match — this file must never
  drift from reality.
- Never mark something "done" in `claude.json` without having actually
  verified it (ran it, tested it, or read the diff).
- Keep entries factual and specific — no vague statuses like "working on
  stuff." Say what changed, in which file, and why.
- If you're not sure whether something is done, mark it `in_progress` or
  `blocked`, not `done`.

## 5. Limitations (must always respect)

- Do not rewrite this file's structure without being asked.
- Do not delete history/entries from `claude.json` — append, don't erase.
  (Old milestones are useful context for later decisions.)
- Do not assume anything about the codebase that isn't in `project.json`
  or verified by directly reading the file in question.
- Never violate the Scope Boundary in §0, for any reason.
- [Add project-specific limitations here, e.g.: coding style/lint rules,
  don't add new dependencies without asking, etc.]

## 6. Sync flag convention

If you edit a file but don't have time/context to update `project.json`
immediately, add its path to `"needs_resync"` in `claude.json`. Any future
session must treat those files as untrusted in `project.json` and read
them directly before relying on them.

## 7. Where things live

- Boundaries → this file, §0 (strict, never overridden by the JSONs)
- Requirements → this file, §2
- Progress / milestones / issues → `claude.json`
- File & function map → `project.json`

---
*Last reviewed: 2026-07-30*