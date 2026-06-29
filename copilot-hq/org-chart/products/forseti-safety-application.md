# Forseti Safety Application

## Status
- **State:** active product slice on `forseti.life`
- **Primary seats:** `pm-forseti`, `ba-forseti`, `dev-forseti`, `qa-forseti`
- **Primary modules:** `forseti_content`

## Product summary
This project covers Forseti's community safety application surfaces already present on `forseti.life`: the safety map experience, safety-factor explanation pages, mobile-app entry points, and the broader "AI-powered community safety" positioning embedded in the live site.

The system is already materially implemented. What exists today is not a placeholder product idea; it is a running safety-oriented application experience with dedicated routes, controllers, templates, and service-layer support.

## Existing system in place

### Canonical implementation references
- `../sites/forseti/web/modules/custom/forseti_content/README.md`
- `../sites/forseti/web/modules/custom/forseti_content/forseti_content.routing.yml`
- `../sites/forseti/web/modules/custom/forseti_content/src/Controller/SafetyController.php`

### Existing user-facing scope
- Homepage messaging centered on AI-powered community safety
- Interactive safety map experience
- Safety factors / seven-dimensions framing
- Mobile app acquisition and beta-testing entry points
- Safety-oriented informational and community pages

### Existing route/domain footprint
- `/`
- `/home`
- `/safety-map`
- `/safety-factors`
- `/mobile-app`
- `/mobile-app/beta-testing`
- `/privacy`
- `/community`

## High-level requirements
1. **Neighborhood safety visibility**
   - Users can access a live safety-map experience that surfaces geographically relevant safety conditions.
   - The product should help users understand place-based risk without requiring insider knowledge of the city.

2. **Explainable safety model**
   - Safety scoring and alerts must be grounded in understandable dimensions, not opaque labels.
   - The existing seven-dimensions framing remains a core requirement for user trust and product coherence.

3. **Actionable guidance**
   - The application should not stop at map display; it should help users decide what to do next.
   - Calls to action such as map review, mobile-app usage, and contextual guidance are part of the product surface.

4. **Community-facing delivery**
   - The product must remain accessible as a public-facing Forseti experience, not a back-office tool.
   - Messaging, routes, and page content should continue to support community onboarding and adoption.

5. **Safety and privacy posture**
   - Safety-related features must be careful about false certainty, privacy leakage, and overclaiming predictive capability.
   - Any future alerting, personalization, or mobile expansion should preserve clear user expectations around data use and confidence levels.

## Near-term planning posture
- Treat this as a **live product stream with existing pages and controllers**, not a blank-slate concept.
- Prioritize product cohesion, route/page quality, underlying data confidence, and safe user messaging.
- Future work should extend the current safety-map, safety-factors, and mobile-app surfaces already in production rather than redefining the application from scratch.
