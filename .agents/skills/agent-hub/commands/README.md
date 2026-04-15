# Command specs (`commands/`)

These files are the **canonical** workflow definitions for agent-hub. Hosts (Claude Code, Cursor, and others) do not load this folder automatically.

## Plug-and-play for users

You only need **one habit**: tell the AI to follow **`commands/agent-hub.md`** (the dispatcher). The model reads **`agent/operating-model.md`**, classifies your goal, then opens **one** follow-on command or **one** architecture doc. You do not have to memorize layer numbers.

## IDE launchers

To trigger the same flows from **Cursor**, create thin files under your **repository root** `.cursor/commands/` that tell the agent to follow the matching file under **`agent-hub/commands/`**. This package does not include a bundled `.cursor/` folder. **Fast path:** attach **`COMMANDS.md`** at the package root and use its copy-paste prompt so the AI writes the launchers. See **`indexes/command-index.md`** for suggested names (**`agent-hub-*`**).

**Rules:** Canonical text stays in **`commands/*.md`**. Wrappers stay short. If something disagrees, fix the wrapper.

Paths inside each spec are relative to the **agent-hub package root** (parent of `commands/`).

Full command list: **`indexes/command-index.md`**.
