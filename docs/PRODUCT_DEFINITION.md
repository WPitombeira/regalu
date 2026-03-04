# Regalu - Product Definition

**Date:** February 18, 2026
**Status:** Synthesized from BA1 (Current State) + BA2 (Product Vision)

---

## 1. What is Regalu?

A family-oriented platform for wishlists and **Amigo Secreto** (Secret Santa). Built for the Brazilian market where Amigo Secreto is a deeply rooted cultural tradition across families, friend groups, and workplaces.

**Core promise:** Create wishlists. Run fair draws. Coordinate via WhatsApp.

### Value Proposition

| Competitor | Gap Regalu fills |
|---|---|
| Amazon Wishlist | No family focus, no Amigo Secreto, no group sharing UX |
| Generic Secret Santa apps | No wishlists, no family focus, English-only, no WhatsApp |
| Pinterest | Not designed for gift coordination, no draw feature |
| Alchimyca (BR startup) | Limited wishlists, no family groups |

**Regalu's edge:** Family-centric + Amigo Secreto + wishlists + WhatsApp, all in one place, Portuguese-first.

---

## 2. Current State (What Exists Today)

**Product readiness: ~25%** (foundation + landing page + basic auth)

### Working

- Landing page (hero, features, newsletter, contact sections)
- Email/password login (session-based)
- Newsletter subscription (model + controller + DB)
- Toast notification system (Livewire events)
- UI component library (navbar, footer, 30+ SVG icons, dark mode)
- CI/CD pipeline (GitHub Actions, Pest tests)
- Laravel 12 + Livewire 3 + TailwindCSS + Octane/RoadRunner

### Scaffolded but incomplete

- Registration form (UI exists, backend handler missing, wire:model binding bugs)
- Dashboard page (shows "Hello [name]" only)
- User settings page (empty shell)
- Feedback/contact form (stub implementation, discards input)
- OAuth buttons (Apple/Google visible, no Socialite integration)

### Not started (routes commented out)

- Wishlist system (no model, no controller, no views)
- Amigo Secreto (no model, no controller, no draw algorithm)
- Family/group management
- Sharing & permissions
- Email notifications

### Data model today

| Model | Fields | Status |
|---|---|---|
| User | id, name, email, password, oauth_tokens, remember_token | Basic |
| Newsletter | id, email, active | Functional |

---

## 3. Target Audience

**Primary:** 35-55F (family organizers) - coordinate gifts, manage family events, heavy WhatsApp users

**Secondary:** 22-35 (young professionals) - organize friend/work group Amigo Secreto, Instagram/TikTok active

### Brazilian market realities

- **WhatsApp:** 99% smartphone penetration, primary coordination channel
- **Mobile-first:** 80%+ internet via mobile
- **PIX:** Universal instant payments (for group gifting)
- **LGPD:** Brazil's GDPR equivalent (privacy compliance required)
- **Seasonal peaks:** Christmas (5-10x), Dia das Maes (May), Dia dos Namorados (Jun), Dia das Criancas (Oct)

---

## 4. Core Feature Set (MVP)

### A. Authentication & Profiles

- Email registration + login + logout
- Password reset (email link)
- Profile settings (name, avatar, password change)
- Mobile-responsive onboarding flow

### B. Family Management

- Create family with shareable invite code + QR
- Invite members via email link
- Accept/reject invitations
- Family roster view
- Family settings (rename, archive)

### C. Wishlist System

- Create/edit/delete wishlists (Christmas, birthday, wedding, generic)
- Add items: manual entry + URL import (title, price, image auto-fetched)
- Privacy levels: PRIVATE, FAMILY, SPECIFIC_PEOPLE
- Share wishlist via link
- Mark items as BOUGHT (hidden from wishlist owner, visible to others)
- Email notification when item marked bought

### D. Amigo Secreto

- Create event: name, budget range, date, type
- Invite participants via email (+ WhatsApp in Phase 2)
- Exclusion rules (couples can't draw each other)
- Execute fair random draw
- Send assignment via email with target's wishlist link
- Reveal mechanism (automatic on event date)
- Past events archive

---

## 5. Proposed Data Model

```
Users
  id, name, email, password, avatar_url, oauth_tokens,
  locale (default: pt-BR), phone, timestamps

Families
  id, name, description, invite_code (unique), creator_id (FK),
  is_archived, timestamps

FamilyMembers
  id, family_id (FK), user_id (FK), role (ADMIN/MEMBER),
  joined_at, timestamps

Wishlists
  id, user_id (FK), family_id (FK, nullable), name, description,
  type (CHRISTMAS/BIRTHDAY/WEDDING/GENERIC),
  privacy (PRIVATE/FAMILY/SPECIFIC), is_archived, timestamps

WishlistItems
  id, wishlist_id (FK), name, description, url, image_url,
  price_min, price_max, category, priority (LOW/MEDIUM/HIGH),
  status (AVAILABLE/BOUGHT/RECEIVED),
  bought_by_user_id (FK, nullable, hidden from owner),
  bought_at, notes, timestamps

WishlistShares
  id, wishlist_id (FK), shared_with_user_id (FK),
  access_level (VIEW/EDIT), timestamps

AmigoSecretoEvents
  id, organizer_id (FK), family_id (FK, nullable),
  name, description, event_code (unique),
  event_type (CHRISTMAS/BIRTHDAY/WEDDING/CASUAL),
  budget_min, budget_max, event_date, reveal_date,
  status (PLANNING/INVITES_SENT/DRAW_PENDING/DRAWS_COMPLETE/REVEALED/COMPLETED),
  is_archived, timestamps

AmigoSecretoParticipants
  id, event_id (FK), user_id (FK),
  status (INVITED/ACCEPTED/DECLINED/WITHDRAWN),
  invite_email, invited_at, accepted_at, timestamps

AmigoSecretoDraws
  id, event_id (FK), drawer_user_id (FK), target_user_id (FK),
  draw_date, revealed_at, timestamps

AmigoSecretoExclusions
  id, event_id (FK), user_a_id (FK), user_b_id (FK),
  reason, timestamps

Notifications
  id, user_id (FK), type, title, message,
  related_entity_type, related_entity_id,
  is_read, action_url, timestamps
```

---

## 6. User Journeys

### Journey 1: Family organizer creates Christmas wishlist

1. Maria registers on Regalu
2. Creates "Familia Silva" family
3. Sends invite links to 5 family members via WhatsApp
4. Members join and create their own wishlists
5. Maria creates "Natal 2026" wishlist with 8 items
6. Sets privacy to FAMILY so all members can see
7. Brother Joao sees her wishlist, buys the headphones, marks as BOUGHT
8. Maria sees "1 item purchased" (doesn't know by whom)

### Journey 2: Organizing an Amigo Secreto

1. Joao creates event "Amigo Secreto Silva 2026"
2. Sets budget R$ 50-150, date Dec 25
3. Adds 8 participants via email
4. Marks 2 couples as exclusions
5. Participants accept invitations
6. Joao clicks "Execute Draw"
7. Each participant receives email with their target + wishlist link
8. On Dec 25, reveals happen automatically

### Journey 3: Buying a gift from someone's wishlist

1. Ana receives Amigo Secreto assignment: she drew Pedro
2. Clicks link to see Pedro's wishlist
3. Sees 6 items sorted by priority
4. Clicks "I bought this" on the drone (R$ 120)
5. Pedro's wishlist now shows "1 item purchased" (no name visible)
6. Other family members see the drone is taken, pick different items

---

## 7. Phased Roadmap

### Phase 1: MVP (12-14 weeks, Target: Aug 2026)

| Weeks | Focus | Deliverables |
|---|---|---|
| 1-2 | Infrastructure | DB schema, mail queue, fix registration bugs |
| 3-4 | Auth & UX | Registration backend, password reset, profile settings |
| 5-6 | Family Management | Create/join families, invite system, roster |
| 7-9 | Wishlists | CRUD, items, privacy, sharing, mark-as-bought |
| 10-11 | Amigo Secreto | Events, participants, draw algorithm, email assignments |
| 12-13 | Testing & Polish | 60+ Pest tests, PHPStan, mobile QA |
| 14 | Pre-launch | Staging deploy, beta testing (50 users) |

**Success criteria:** 500 users, 200 families, 50 events, 70% wishlist creation within 7 days

### Phase 2A: Social & WhatsApp (Q4 2026)

- Twilio WhatsApp Business API (invites, assignments, reminders)
- Push notifications
- Family activity feed
- Notification preferences

### Phase 2B: E-Commerce (Q1 2027)

- Mercado Livre, Americanas, Magazine Luiza API integrations
- Auto-fetch product details from URLs
- Affiliate links (monetization: 2-5% commission)
- Price comparison across platforms

### Phase 2C: Advanced Features (Q2 2027)

- Group gifting (pool money via PIX for expensive items)
- Gift history & tracking
- Smart suggestions based on past wishlists

### Phase 3: Scale & Premium (H2 2027)

- Corporate/team tier (bulk events, audit logs, approval workflows)
- Freemium model (Free: 5 wishlists, 1 family / Premium: R$ 10/mo unlimited)
- Recurring annual events (auto-create next year's Amigo Secreto)
- React Native mobile app

---

## 8. Known Bugs to Fix First

Found during BA1 audit:

1. **Registration form binding errors** (`login-form.blade.php` lines 98, 102): email and password fields both use `wire:model="name"` instead of proper bindings
2. **Missing `register()` handler** in `LoginForm.php`: form submits to nowhere
3. **Feedback form stub** (`Home.php:31`): unused `$Teste = 1` variable, shows both success and error notifications
4. **Hardcoded user data** (`user/card.blade.php`): shows "Bonnie Green" instead of `auth()->user()->name`

---

## 9. Monetization Strategy

| Phase | Revenue Stream | Projected Monthly |
|---|---|---|
| Phase 1 | None (user acquisition) | R$ 0 |
| Phase 2A | Premium subscriptions (early) | R$ 100-500 |
| Phase 2B | Affiliate commissions (e-commerce) | R$ 1.5K-3K |
| Phase 3 | Premium + Affiliate + Corporate | R$ 13K+ |

**Break-even:** Q2 2027 (~9 months post-launch)

---

## 10. Risks & Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Draw algorithm perceived unfair | Users leave | Extensive testing, transparency blog post, redo option |
| Christmas traffic spike (10x) | Infrastructure crash | Octane/RoadRunner, load testing, CDN, horizontal scaling |
| WhatsApp API costs | ROI negative | Monitor per-message costs, hybrid email+WhatsApp |
| Low affiliate revenue | Monetization fails | Freemium + corporate tier as backup |
| LGPD compliance | Legal risk | Consult BR lawyer pre-launch, explicit consent, data deletion |

---

## 11. Team Needs

| Role | Count | Phase |
|---|---|---|
| Product Manager | 1 | Phase 1+ |
| Senior Backend (Laravel) | 1 | Phase 1+ |
| Mid Frontend (Livewire/Blade) | 1 | Phase 1+ |
| Designer (part-time) | 1 | Phase 1+ |
| QA/Test Automation | 1 | Phase 1 (can be shared) |
| Marketing (part-time) | 1 | Phase 2+ |
