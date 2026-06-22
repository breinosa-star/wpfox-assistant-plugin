# Gray Fox — Product Guide

## What Is Gray Fox?

Gray Fox is a WordPress plugin that adds an AI-powered chat widget and a private knowledge base to any WordPress website. You upload your own documents — PDFs, Word files, plain text — and the chatbot learns from them. When a visitor asks a question, the AI searches your knowledge base and replies using your content.

Gray Fox is built for small and mid-sized businesses that want to automate customer questions, capture leads, and offer booking — without hiring developers or leaving WordPress.

---

## Plans and Pricing

Gray Fox has four plans. All plans include the AI chat widget, document upload, lead capture, and multilingual support.

### Trial
- **Price:** Free
- **Documents:** Up to 5
- **Chats:** 200 per month
- **Best for:** Testing the plugin before committing. No credit card required.

### Starter
- **Price:** $29 / month
- **Documents:** Up to 20
- **Chats:** 2,000 per month
- **Best for:** Small businesses with a stable FAQ — a salon, a solo practitioner, a local shop.

### Growth
- **Price:** $59 / month
- **Documents:** Up to 100
- **Chats:** 10,000 per month
- **Includes:** Google Drive auto-sync, appointment booking via Google Calendar
- **Best for:** Growing businesses that update their content regularly and want booking automation.

### Pro
- **Price:** $99 / month
- **Documents:** Unlimited
- **Chats:** Unlimited
- **Includes:** Everything in Growth, plus webhook callbacks, conversation analytics, priority support, and the ability to bring your own API key (OpenAI, Anthropic, Gemini, or Groq)
- **Best for:** Businesses with large or frequently updated knowledge bases, technical teams, and anyone with GDPR or compliance requirements.

> Pricing questions? We'll have someone reach out with the full breakdown — just share an email and we'll send it over.

---

## Features

### Document Upload and Knowledge Base

You can upload PDF files, Word documents (.docx), and plain text files directly from the WordPress admin dashboard under **Gray Fox → Knowledge Base**. The plugin processes each document automatically — no retraining, no waiting. Once a file is processed, the chatbot can answer questions from it immediately.

- Supported formats: PDF, DOCX, TXT
- Documents are stored on your own WordPress server, not on our servers
- You can add, remove, or replace documents at any time
- There is no limit to document size, though very large files (100+ pages) may take a minute to process

For existing customers adding a new document: go to **Gray Fox → Knowledge Base → Upload Document**, select your file, and click Upload. The chatbot will start using it right away — no additional steps needed.

### AI Chat Widget

The chat widget appears on your website as a small button (bottom-right by default). Visitors click it to open a chat window and ask questions in plain language. The AI searches your knowledge base and replies using your content.

- Fully customizable: change the widget color, title, and welcome message from **Gray Fox → Settings**
- Works as a floating bubble or embedded inline in any page using the `[grayfox_chat]` shortcode
- The widget is responsive and works on mobile, tablet, and desktop
- Responses are in the visitor's language — if they write in Spanish, the bot replies in Spanish

### Appointment Booking

Gray Fox integrates with Google Calendar to let visitors book appointments directly in the chat. When a visitor asks to book, the bot collects the details and creates an event on your calendar.

- Available on **Growth** and **Pro** plans
- Requires connecting a Google Calendar account in **Gray Fox → Settings → Booking**
- No third-party booking software required

### Google Drive Auto-Sync

Connect a Google Drive folder and Gray Fox will automatically update your knowledge base whenever you add or edit a file in that folder. This is useful if you update your pricing guide, schedule, or menu regularly.

- Available on **Growth** and **Pro** plans
- Supports PDF, DOCX, and Google Docs (exported as PDF)
- Sync runs once per hour

### Lead Capture

When a visitor asks about pricing or expresses interest in getting started, the bot collects their name and email address and saves the lead to **Gray Fox → Conversations**. You also receive an email notification.

- Lead capture happens naturally in conversation — the bot does not use pop-ups
- Leads are stored in your WordPress database, on your server
- Export leads as CSV from the Conversations dashboard

### Webhook Callbacks (Pro)

When a lead is captured, Gray Fox can send a POST request to any URL you specify — a Zapier webhook, a CRM, Slack, or your own endpoint.

- Configure the webhook URL in **Gray Fox → Settings → Integrations**
- Payload includes: name, email, conversation summary, timestamp
- Useful for connecting Gray Fox to HubSpot, Salesforce, Airtable, or any tool that accepts webhooks

### Conversation Analytics (Pro)

The **Gray Fox → Conversations** dashboard shows a log of every conversation, including the full transcript, which documents were used to answer questions, and whether a lead was captured.

- Filter by date range, lead status, and conversation outcome
- Identify which topics come up most often to prioritize KB content
- Export as CSV

### Bring Your Own API Key (Pro)

By default, Gray Fox uses a shared API connection. On the Pro plan, you can plug in your own API key from OpenAI, Anthropic (Claude), Google Gemini, or Groq.

- Your API key is stored encrypted in your WordPress database
- Using your own key means your data is governed by your agreement with the provider
- Useful for teams that already have enterprise data processing agreements with OpenAI or Anthropic for GDPR purposes

### Multilingual Support

The chatbot automatically detects the visitor's language and replies in the same language. No configuration is needed. This works for Spanish, French, German, Portuguese, Italian, and any other language supported by the underlying AI model.

---

## Data, Privacy, and GDPR

### Where is my data stored?

Everything — your uploaded documents, conversation logs, and captured leads — is stored in your own WordPress database on your own hosting server. Gray Fox does not send your data to our servers. We never see your documents or your visitors' conversations.

### Who has access to conversation data?

Only you. Conversations are stored in your WordPress database, which is on your hosting account. Your hosting provider has access to the server (as they always do), but Gray Fox as a company does not.

### Do you train AI on my documents?

No. Your documents are used only to answer questions from your visitors. They are never sent to us, never used to train AI models, and never shared with third parties. When a visitor asks a question, the relevant excerpt from your document is sent to the AI model (OpenAI, Anthropic, etc.) as part of the query — but this is governed by your agreement with that provider, not ours.

### GDPR Compliance

Because your data lives on your own WordPress server (your hosting), you control it entirely. Gray Fox does not act as a data processor for your visitors' personal data — your hosting provider does. This makes GDPR compliance straightforward:

- Add Gray Fox to your privacy policy as a plugin that stores conversation data on your server
- Visitors can request deletion of their data — you can delete conversations from the Gray Fox dashboard
- If you use your own API key (Pro plan), you can sign a Data Processing Agreement directly with OpenAI or Anthropic

### What happens to my data if I cancel?

Nothing. Gray Fox is a WordPress plugin. If you deactivate or uninstall it, your documents and conversation logs remain in your WordPress database until you choose to delete them. There is no account to close, no cloud storage to wipe. Your data stays on your server.

---

## Setup and Getting Started

Gray Fox installs like any WordPress plugin:

1. Upload the plugin zip to **WordPress Admin → Plugins → Add New → Upload Plugin**
2. Activate the plugin
3. Go to **Gray Fox → Settings** and configure your widget (name, color, welcome message)
4. Go to **Gray Fox → Knowledge Base** and upload your first document
5. Add the widget to your site — it appears automatically on all pages, or use `[grayfox_chat]` to embed it on a specific page

**Do I need a developer?** No. Setup takes about 15 minutes and requires no coding. If you want to customize colors or embed the chat in a specific location, that's handled through the WordPress admin with no code.

**Can I change plans?** Yes. Upgrade or downgrade at any time from **Gray Fox → Settings → Plan**. Changes take effect immediately. If you downgrade and have more documents than your new plan allows, existing documents remain but you will not be able to upload new ones until you are under the limit.

**How long does setup take?** Most users are live within 30 minutes. Uploading and processing a large library of documents (20+) may take an additional 10–15 minutes.

---

## What Gray Fox Does Not Do

- **Custom software development.** Gray Fox is a WordPress plugin, not a development agency. We do not build custom mobile apps, APIs, or bespoke software.
- **Video or image responses.** The chat widget is text-only. It cannot display videos, image carousels, or interactive media inside the chat window.
- **Enterprise multi-instance management.** Gray Fox is designed for single-site or small multi-site WordPress installations. Managing hundreds of independent instances from a central dashboard is not a current feature.
- **Inbound phone or SMS.** Gray Fox works on websites only. It does not handle phone calls, SMS, or WhatsApp messages.

---

## Contact and Support

For pricing details, custom demos, or questions not covered here, contact us at **hello@grayfox.ai**. A member of our team typically responds within one business day.

Existing customers can reach support at **support@grayfox.ai** or through the in-dashboard help widget.
