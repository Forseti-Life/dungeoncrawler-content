# Node Mailbox — dev-laptop

Git-based inter-node message queue for the dev-laptop worker node.

| Dir | Purpose |
|---|---|
| `inbox/` | Messages addressed TO dev-laptop. Written by master, read by worker. |
| `outbox/` | Processed messages FROM dev-laptop. Archive after handled. |

Messages are Markdown files with YAML front matter.
See `scripts/node-send.sh` and `scripts/node-recv.sh`.
