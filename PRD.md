# Product Requirements Document (PRD)
# Kudosly — Testimonial Collection SaaS

**Version:** 1.0  
**Status:** MVP Specification  
**Platform:** Web Application  
**Framework:** Laravel 13  
**Product Inspiration:** Testimonial.to

---

## 1. Product Overview

Kudosly is a minimal SaaS application that allows businesses to collect, manage, and display customer testimonials.

Users can create branded testimonial collection pages called **Spaces**, share public links with customers, manage submitted testimonials, and embed selected testimonials on external websites.

The application uses a freemium subscription model with Stripe billing.

### Primary Goals

- Make testimonial collection simple and fast.
- Allow customers to submit testimonials without registration.
- Provide an intuitive dashboard for testimonial management.
- Offer customizable, embeddable testimonial widgets.
- Monetize through Free and Pro plans.

## 2. Technology Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP |
| Frontend | Blade, Livewire, Tailwind CSS |
| Database | MySQL |
| Authentication | Laravel authentication |
| Payments | Stripe Checkout + Laravel Cashier |
| Charts | Chart.js |
| File Storage | Laravel Storage |
| Background Jobs | Laravel Queues |
| Deployment | Standard PHP/Laravel Hosting |

Use server-rendered Laravel architecture. Do not introduce Next.js, React, or unnecessary microservices.

## 3. User Roles

### Account Owner
- Register, log in, and manage their account.
- Create and configure Spaces.
- View and manage testimonials.
- Generate embed widgets.
- View analytics and manage billing.

### Customer (Public Visitor)
- Visit a public Space URL.
- Submit a testimonial without authentication.
- Provide requested details, rating, and sharing consent.
- Receive confirmation after submission.

No team roles or administrative back-office interface are required for MVP.

## 4. Authentication

Implement secure authentication with:

- User registration
- Login and logout
- Password reset
- Profile management
- Password hashing and CSRF protection

New accounts automatically receive the Free plan.

All dashboard routes require authentication. Users can only access their own Spaces, testimonials, widgets, and billing information.

## 5. Space Management

### 5.1 Create a Space

Authenticated users can create Spaces from the dashboard.

Each Space must support:

| Field | Requirement |
|---|---|
| Space Name | Required |
| Public Slug | Required, unique |
| Page Title | Required |
| Subtitle | Optional |
| Testimonial Request / Ask | Required |
| Rating Enabled | Toggle |
| Theme | Select predefined theme |
| Custom Fields | Configurable |

### 5.2 Configurable Collection Fields

**Default fields:**

- Name — always required
- Email — always required

**Optional predefined fields:**

- Company Name
- Social Profile URL
- Profile Photo

Each optional field can be enabled or disabled and independently marked required or optional.

Field settings must be stored per Space and used for public form rendering and server-side validation.

### 5.3 Predefined Themes

Provide three themes:

1. Minimal Light
2. Modern Dark
3. Soft Gradient

Display a live preview while selecting themes.

### 5.4 Space Creation Success

After successfully creating a Space:

- Display a success message.
- Show its public URL: `/s/{slug}`
- Provide a Copy Link button.
- Offer View Space and Go to Dashboard actions.

Users must also be able to edit and delete their Spaces.

## 6. Public Testimonial Collection

Public Space pages must be accessible without authentication.

### Collection Page

Display:

- Space title and subtitle
- Testimonial request
- Configured form fields
- Testimonial text input
- Optional 1–5 star rating
- Social sharing permission checkbox
- Submit button

Testimonial text, customer name, and email are always required.

The rating input appears only when enabled for the Space.

### Consent

Include an explicit, unchecked consent checkbox:

"I give permission for my testimonial to be publicly displayed and shared on websites or social media."

Customers can submit without granting public-sharing permission. Such submissions remain private and cannot appear in public widgets.

### Submission Rules

- Validate all fields on the server.
- Validate uploaded profile photos for supported image types and file size.
- Apply rate limiting and spam prevention.
- Enforce the Space's plan-based testimonial limit.
- Store testimonials as pending by default.
- Never display customer email addresses publicly.

### Success Screen

After submission, display a friendly confirmation:

"You're officially awesome! 🎉 Your kind words just made someone's day."

## 7. Dashboard

The dashboard should provide a concise overview of all Spaces and testimonials.

### Statistics Cards

- Total Spaces
- Total Testimonials
- Unique Customers (by normalized email)
- Testimonials This Month
- Average Rating (where ratings exist)
- Current Plan and Usage

### Analytics Chart

Display a line or bar chart showing daily testimonial submissions.

Support:

- Last 7 days
- Last 30 days
- Last 90 days

Show recent testimonials and recent Spaces.

### Free Plan Upgrade Banner

Free users should see a nonintrusive upgrade banner with a link to the Billing page.

Paid users should not see this banner.

## 8. Testimonial Inbox

Provide a central inbox to manage submitted testimonials.

### Display

Each testimonial item should show:

- Customer name and profile photo
- Space name
- Testimonial text
- Star rating, if applicable
- Submission date
- Moderation status
- Favorite status
- Wall of Love status

### Actions

| Action | Behavior |
|---|---|
| Favorite | Pin testimonial above nonfavorites |
| Heart | Add/remove from Wall of Love |
| Approve | Make eligible for public display |
| Hide | Remove from public display |
| Edit | Update testimonial details |
| Delete | Permanently delete after confirmation |

Provide an ellipsis menu for secondary actions.

### Filtering

Support filtering by:

- Space
- Status
- Rating
- Favorites
- Date

Include text search and pagination.

Only approved, nonhidden testimonials with sharing consent may be displayed publicly.

Favorite ordering should not override these visibility restrictions.

## 9. Wall of Love

Each Space can have a public Wall of Love page:

`/wall/{slug}`

Display testimonials explicitly marked with a heart icon.

Include:

- Responsive card layout
- Customer name and photo
- Testimonial text
- Rating when available
- Space branding

Only publicly eligible testimonials may appear.

## 10. Embeddable Widgets

Users can generate customizable widgets for their Spaces.

### Available Layouts

1. Masonry Grid
2. Carousel

### Customization Options

- Layout selection
- Light / Dark mode
- Background color
- Animation enabled / disabled
- Number of testimonials displayed

Display a live preview before generating embed code.

### Embed Output

Provide copyable iframe and JavaScript embed snippets.

Example iframe:

```html
<iframe
  src="https://your-domain.com/embed/WIDGET_ID"
  width="100%"
  height="500"
  loading="lazy"
  title="Customer Testimonials">
</iframe>
```

Example JavaScript:

```html
<div data-kudosly-widget="WIDGET_ID"></div>
<script
  async
  src="https://your-domain.com/widgets/embed.js">
</script>
```

### Technical Requirements

- Widgets must work on external websites.
- Widgets must be responsive.
- Public widgets must not require login.
- Widget settings must persist in the database.
- JavaScript embeds should avoid CSS conflicts with host websites.
- Embed endpoints must expose only approved, consented, visible testimonials.
- Include safe HTML escaping and reasonable caching.

## 11. Pricing and Subscription Plans

| Feature | Free | Pro |
|---|---|---|
| Price | $0 | $999 |
| Spaces | 3 | 25 |
| Testimonials per Space | 100 | 1,000 |
| Custom Themes | Yes | Yes |
| Analytics | Yes | Yes |
| Embeddable Widgets | Yes | Yes |
| Wall of Love | Yes | Yes |

**Pricing decision required:** Confirm whether $999 is monthly or annual before configuring Stripe recurring billing.

### Plan Enforcement

- Enforce limits server-side.
- Prevent creating additional Spaces after reaching the plan limit.
- Prevent new submissions when a Space reaches its testimonial limit.
- Display usage counters and upgrade prompts.
- Existing records remain accessible if a subscription ends, but Free limits apply to new Spaces and submissions.

## 12. Billing and Stripe Integration

### Billing Page

Display:

- Current subscription plan
- Free vs. Pro comparison
- Usage information
- Upgrade to Pro button
- Subscription status

### Upgrade Workflow

1. User clicks Upgrade.
2. Backend creates a Stripe Checkout session.
3. User completes payment on Stripe.
4. Stripe redirects to the success page.
5. A verified Stripe webhook confirms payment/subscription status.
6. The application activates the Pro plan.
7. Billing page reflects the updated plan.

### Stripe Requirements

- Use Laravel Cashier where appropriate.
- Store Stripe customer and subscription identifiers.
- Verify webhook signatures.
- Handle duplicate webhook events safely.
- Handle successful payments, failed payments, renewals, and cancellations.
- Provide subscription management through Stripe's customer portal.
- Never store raw payment card details.
- Never activate Pro based solely on the Checkout success redirect.

## 13. Database Structure

Create migrations and Eloquent models for:

### users
- id
- name
- email
- password
- stripe_id
- timestamps

### plans
- id
- name
- slug
- price
- stripe_price_id
- max_spaces
- max_testimonials_per_space

### spaces
- id
- user_id
- name
- slug
- title
- subtitle
- ask
- theme
- rating_enabled
- timestamps

### space_fields
- id
- space_id
- field_key
- enabled
- required
- sort_order

### testimonials
- id
- space_id
- customer_name
- customer_email
- company_name
- social_url
- profile_photo_path
- testimonial_text
- rating (nullable)
- sharing_consent
- status
- is_favorite
- is_wall_of_love
- timestamps

### widgets
- id
- space_id
- public_id
- layout
- theme_mode
- background_color
- animation_enabled
- display_limit
- timestamps

### billing
Use Laravel Cashier subscription tables, with relevant Stripe customer and subscription metadata.

Additional structured tables may be introduced where necessary.

## 14. Design Requirements

Create a premium-looking, minimal SaaS interface.

### Visual Direction

- Clean sidebar navigation
- Spacious dashboard cards
- Subtle borders and shadows
- Modern typography
- Consistent color palette
- Responsive desktop and mobile layouts
- Helpful empty states
- Toast notifications
- Clear loading and error states

### Dashboard Navigation

- Overview
- Spaces
- Inbox
- Wall of Love
- Widgets
- Billing
- Settings

Prioritize simplicity over feature density.

## 15. Security and Reliability

Implement:

- Laravel authentication and authorization policies
- CSRF protection
- Server-side input validation
- Safe file upload handling
- Rate limiting on public submissions
- Output escaping and XSS protection
- Secure Stripe webhook verification
- Database transactions where required
- Indexed database queries
- Pagination for large testimonial collections
- Environment-based secrets configuration

Customer email addresses and private testimonials must never be exposed through public endpoints.

## 16. Out of Scope for MVP

Do not implement:

- Video testimonial recording
- AI-generated testimonials
- AI sentiment analysis
- Team collaboration
- Third-party CRM integrations
- Custom domains
- Email marketing automation
- Additional paid tiers
- Advanced branding customization
- Native mobile applications

Focus on a reliable, polished text-testimonial experience.

## 17. Development Phases

### Phase 1 — Foundation
- Inspect existing Laravel project.
- Configure MySQL.
- Implement authentication.
- Create migrations and models.
- Build dashboard layout.

### Phase 2 — Spaces and Collection
- Space CRUD
- Configurable fields
- Themes
- Public submission form
- Submission success page

### Phase 3 — Management
- Testimonial inbox
- Approval and hiding
- Favorites
- Wall of Love
- Dashboard statistics and charts

### Phase 4 — Widgets
- Widget editor
- Masonry and carousel layouts
- Live preview
- iframe and JavaScript embeds

### Phase 5 — Monetization
- Free and Pro limits
- Stripe Checkout
- Webhook processing
- Billing page
- Subscription management

### Phase 6 — Testing and Polish
- Feature tests
- Authorization tests
- Subscription limit tests
- Responsive UI review
- Deployment preparation

## 18. MVP Acceptance Criteria

The MVP is complete when:

- [ ] A new user can register and log in.
- [ ] Free users can create up to three Spaces.
- [ ] Users can customize fields, rating, and themes.
- [ ] Public visitors can submit testimonials without accounts.
- [ ] Testimonials appear in the correct owner's inbox.
- [ ] Owners can approve, edit, hide, favorite, heart, and delete testimonials.
- [ ] Public widgets show only eligible testimonials.
- [ ] Masonry and carousel embeds function on external websites.
- [ ] Dashboard statistics reflect real database records.
- [ ] Free plan limits are enforced.
- [ ] Stripe subscription payments update account entitlements through verified webhooks.
- [ ] Users can view and manage their subscriptions.
- [ ] All key pages work on desktop and mobile.
- [ ] Automated tests cover critical business rules.

## 19. Instructions for AI Coding Agent

1. Work inside the existing Laravel project.
2. Inspect the current codebase before implementing anything.
3. Preserve Laravel 13 compatibility and existing working configuration.
4. Follow standard Laravel conventions, Eloquent relationships, Form Requests, Policies, and migrations.
5. Prefer Blade and Livewire; avoid unnecessary frontend frameworks.
6. Implement features phase by phase.
7. Use seeders for demo content.
8. Write meaningful tests for each major feature.
9. Never hardcode API keys or Stripe secrets.
10. Request approval before destructive database operations or major architectural changes.
11. After each phase, explain what was built, how to test it, and how to run it locally.
12. Do not begin implementation until the outstanding pricing decision is resolved and the implementation plan is approved.

**Final Deliverable:** A functional Laravel SaaS application for collecting, managing, and embedding text testimonials, with authentication, analytics, and Stripe-powered subscriptions.