# Node Mailbox — master

Git-based inter-node message queue for the master node.

| Dir | Purpose |
|---|---|
| `inbox/` | Messages addressed TO master. Written by dev-laptop, read by master CEO. |
| `outbox/` | Processed messages FROM master. Archive after handled. |

Messages are Markdown files with YAML front matter.
See `scripts/node-send.sh` and `scripts/node-recv.sh`.
