1. Identity & Workspace Management
SponsorFlow uses a Multi-Tenant Workspace model. Data is owned by the Workspace, not the individual user.

A. Dynamic User Workspaces
Workspace Creation: Upon signup, users create a Workspace. They must choose a "Type" (Creator or Brand).

Switchable Identities: A single User can own/join multiple Workspaces. A "Workspace Switcher" UI allows them to jump between their Creator dashboard and their Brand dashboard.

Custom Slugs: Creators generate a custom URL (e.g., sponsorflow.app/sarah-content) that acts as their public storefront.

B. Team Management (RBAC via Laratrust)
Custom Roles: Workspaces can invite team members via email.

Creator Roles: * Owner: Full access + Stripe Connect management.

Manager: Can manage inventory, approve brands, and submit proof.

Brand Roles:

Admin: Can authorize payments and manage billing.

Contributor: Can upload ad assets (copy/images) only.

2. Dynamic Inventory Engine (The "Product" Builder)
There is no hardcoded data. Creators build their own product offerings from the ground up.

A. Product Type Definition
Creators define what they sell.

Custom Attributes: A creator defines a "Product" (e.g., "Podcast Mid-Roll").

Dynamic Requirements: For each product, the creator defines the "Asset Metadata" they need from the brand:

Example: For a "Newsletter Ad," the creator adds requirements for: "Logo (PNG/JPG)," "Headline (Max 60 chars)," and "Body Text (Max 300 chars)."

B. Slot Management (The Inventory)
Calendar-Based Slots: Creators map their "Products" to specific dates.

Dynamic Pricing: Each specific slot can have a unique price based on demand (e.g., a "Black Friday" slot costs more than a "Tuesday" slot).

Inventory Status: Slots transition through states: Available → Reserved (Unpaid) → Booked (Paid) → Processing (Assets Uploaded) → Completed.

3. The Brand "Storefront" & Checkout
A high-converting, public-facing interface for advertisers.

A. The Creator Profile
Visual Catalog: A Flux-powered UI displaying all active "Products" a creator has built.

Real-time Availability: A calendar view that pulls from the Creator's slots table. Dates already booked are automatically greyed out.

B. Integrated "Atomic" Checkout
To ensure a "Client-Ready" experience, payment and asset collection happen in one flow:

Selection: Brand selects a date/slot.

Asset Upload: The UI dynamically generates a form based on the Creator's "Requirements" (defined in Section 2A).

Payment: Integrated Stripe Checkout. The payment is captured and held in "Escrow" (Pending status) until fulfillment.

4. Fulfillment & Trust Logic (The "Escrow" System)
This prevents "Scams" and ensures the $1,000/mo value proposition.

A. The Approval Bridge
Right of Refusal: Once a brand pays, the Creator gets a notification. They have a 48-hour window to "Approve" or "Reject" the brand.

Refund Logic: If rejected, the system triggers an automatic Stripe refund and puts the slot back on the market.

B. Proof of Performance (PoP)
Submission: To trigger the payout, the Creator must upload "Proof" (a URL or screenshot).

Brand Review Period: Once proof is submitted, a 48-hour countdown starts. The Brand can "Dispute" the ad if it doesn't meet the agreed requirements.

Automatic Payout: If no dispute is filed within 48 hours, the system moves the funds from the Platform/Escrow to the Creator's Stripe balance.

5. Automated Communications (The "Admin Saver")
The platform acts as an automated assistant to prevent "Messy" manual emails.

The "Nag" Engine: Automated emails/notifications to the Brand if they have a booked slot but haven't finished uploading assets as the "Run Date" approaches.

The Receipt Engine: Professional PDF invoices generated for the Brand, and "Earnings Statements" for the Creator.

The Conflict Handler: Notifications to the Admin (You) if a Brand files a "Dispute," allowing for manual mediation.

6. Monetization & Business Rules
Platform Success Fee: A dynamic % taken from every transaction.

Subscription Upgrades: "Pro" Workspaces unlock custom domains, advanced analytics, and the ability to remove "Powered by SponsorFlow" branding.