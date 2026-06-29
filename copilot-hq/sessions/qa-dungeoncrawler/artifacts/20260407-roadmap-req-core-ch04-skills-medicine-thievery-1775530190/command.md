- command: |
    Roadmap requirements verification: core/ch04 — core/ch04 — Individual Skills: Medicine through Thievery

    **Verify requirements 1688–1748 (57 reqs)** against the production codebase.

    Book: core, Chapter: ch04
    Sections to verify:
  - Medicine (Wis)
  - Nature (Wis)
  - Occultism (Int)
  - Performance (Cha)
  - Religion (Wis)
  - Society (Int)
  - Stealth (Dex)
  - Survival (Wis)
  - Thievery (Dex)

    For each section:
    1. Locate the implementing service/method in the codebase under:
       `/home/ubuntu/forseti.life/sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/`
    2. Cross-reference against `COMBAT_ENGINE_ARCHITECTURE.md` in that module
    3. Return PASS (service name + method) or BLOCK (gap description + suggested feature ID)

    Verify each skill's action variants (Treat Wounds, Command Animal, Identify Magic, Perform, Decipher Writing, Sneak, Track, Pick a Lock, etc.) are implemented. Return PASS/BLOCK per skill section.

    On completion:
    - PASS sections: PM will run `drush dungeoncrawler:roadmap-set-status implemented --book=core --chapter=ch04 --section="<section>"`
    - BLOCK sections: PM will create/link feature pipeline items

    Feature reference: `features/dc-cr-skill-system/feature.md`
    DB query to see reqs: `sudo mysql dungeoncrawler -e "SELECT id, section, req_text FROM dc_requirements WHERE book_id='core' AND chapter_key='ch04' AND status='pending' ORDER BY id;"`
- Agent: qa-dungeoncrawler
- Status: pending
