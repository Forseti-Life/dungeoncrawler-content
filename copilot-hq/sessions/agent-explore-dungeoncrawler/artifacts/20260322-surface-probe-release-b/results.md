# Surface Probe: dungeoncrawler release-b
Date: 2026-03-22T21:56Z
Environment: local http://localhost:8080

| Route | Status | Expected | Pass? |
|---|---|---|---|
| `/` | 200 | 200 | ✓ |
| `/robots.txt` | 200 | 200 | ✓ |
| `/admin` | 403 | 403 (auth-gated) | ✓ |
| `/dungeoncrawler/traits` | 403 | 403 (auth-gated) | ✓ |
| `/api/character/1/traits` | 403 | 403 (auth-gated) | ✓ |
| `/dungeoncrawler/character/create` | 404 | 404 (pending dev delivery) | expected |
| `/dungeoncrawler/character/leveling` | 404 | 404 (pending dev delivery) | expected |

## Summary
- dc-cr-ancestry-traits (commits e97a248b5, 71aa8d924): routes live, auth-gated correctly.
- dc-cr-character-leveling, dc-cr-clan-dagger, dc-cr-dwarf-heritage-ancient-blooded: pending dev delivery; 404 is expected.
