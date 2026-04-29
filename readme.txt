=== GrayFox AI Assistant ===
Plugin Name: GrayFox AI Assistant
Contributors: breinosa
Tags: chatbot, ai, knowledge base, chat widget, theme builder
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chatbot with a RAG knowledge base, site builder, and theme builder for WordPress.

== Description ==

GrayFox AI Assistant adds an AI-powered chatbot to your WordPress site, trained on your own content. Upload your documents, configure your preferred AI provider, and your visitors get accurate answers drawn directly from your knowledge base.

**Chatbot Widget**

A floating chat widget appears on the front end of your site. Visitors can ask questions and receive answers grounded in your uploaded documents. The widget can also be embedded anywhere using the `[grayfox_chat]` shortcode. No registration required for visitors.

**Knowledge Base (RAG)**

Upload PDFs, Word documents (.docx), plain text, CSV, and Markdown files. GrayFox summarizes each document and stores the content in a local database. When a visitor asks a question, the plugin retrieves the most relevant sections and passes them to your configured LLM — no content leaves your server until that retrieval step.

Free tier: up to 15 documents.

**Site Builder**

Generate a full set of WordPress pages from a simple sitemap. The AI drafts page content based on your business description. Each page is created as a draft so you can review and revise before publishing.

**Theme Builder**

Generate a complete block-based (Full Site Editing) WordPress theme from your brand profile — logo, colors, fonts, and tone. The theme is installed and ready to activate. Bootstrap 5 is bundled inside each generated theme (not in the plugin itself).

Free tier: up to 3 saved themes.

**Supported LLM Providers**

OpenAI, Anthropic (Claude), Google Gemini, and Groq. You supply your own API key — GrayFox never proxies your requests through its own servers.

**Privacy**

Visitor chat messages and captured contact details are stored in your WordPress database. Messages are sent to the LLM provider you configure. See the Privacy Policy section for full details.

== Installation ==

1. Upload the plugin zip file through **Plugins > Add New > Upload Plugin**, or extract the folder to `wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu.
3. Go to **GrayFox > Settings** and enter your LLM API key.
4. Upload documents under **GrayFox > Knowledge Base**.
5. The chat widget is enabled by default. Visit your site front end to see it.

== Frequently Asked Questions ==

= Do I need to register or create an account? =
No. All free features work without registering. An optional license key field is available for future paid add-ons; leaving it blank has no effect on free functionality.

= Which LLM providers are supported? =
OpenAI, Anthropic (Claude), Google Gemini, and Groq. You configure the provider, model, and API key in GrayFox > Settings.

= Does the plugin work without an API key? =
The plugin activates and the admin panel is accessible without an API key. AI-powered features (chatbot responses, site generation, theme generation) require a valid API key from your chosen provider.

= How many documents can I add to the Knowledge Base? =
The free plan supports up to 15 active documents.

= How many themes can I build? =
The free plan supports up to 3 saved themes.

= Where is visitor data stored? =
All data is stored in your WordPress database in tables prefixed with `wp_grayfox_`. Nothing is sent to GrayFox servers. Visitor messages are sent to the LLM API provider you configure.

= Can I use the chatbot without the floating widget? =
Yes. Use the `[grayfox_chat]` shortcode to embed the chatbot inline on any page or post.

= Is Bootstrap included in the plugin? =
No. Bootstrap is not included in the plugin itself. When you generate a theme with the Theme Builder, Bootstrap 5.3.3 is downloaded and bundled inside the generated theme's own assets folder.

== External Services ==

This plugin connects to external services as described below. All connections are initiated by the site administrator's configuration — no external calls are made on plugin activation.

**LLM API (user-configured)**
Chat responses, document summarization, site page generation, and theme generation are sent to the LLM API provider you choose in Settings. Visitor messages and your knowledge base content are included in these requests.
- OpenAI: https://openai.com — Terms: https://openai.com/policies/terms-of-use — Privacy: https://openai.com/policies/privacy-policy
- Anthropic: https://anthropic.com — Terms: https://www.anthropic.com/legal/consumer-terms — Privacy: https://www.anthropic.com/legal/privacy
- Google Gemini: https://ai.google.dev — Terms: https://ai.google.dev/gemini-api/terms — Privacy: https://policies.google.com/privacy
- Groq: https://groq.com — Terms: https://groq.com/terms-of-use/ — Privacy: https://groq.com/privacy-policy/

**Unsplash API (opt-in)**
The Site Builder can search for royalty-free images from Unsplash. This feature is only activated if you enter an Unsplash API key in Settings. No requests are made without a key.
- https://unsplash.com — Terms: https://unsplash.com/terms — Privacy: https://unsplash.com/privacy

**GrayFox License API (opt-in)**
The Settings page includes an optional license key field for paid add-ons. A validation request is sent to `api.grayfox.io` only if you enter a license key. No request is made if the field is left blank.
- https://api.grayfox.io — Terms: https://grayfox.io/terms — Privacy: https://grayfox.io/privacy

== Third Party Libraries ==

The following libraries are distributed with this plugin:

* **smalot/pdfparser** 2.x — GNU Lesser General Public License 3.0 — https://github.com/smalot/pdfparser
* **Symfony Polyfill mbstring** — MIT License — https://github.com/symfony/polyfill

The following libraries are **not bundled in the plugin**. They are downloaded and bundled inside each WordPress theme generated by the Theme Builder at generation time:

* **Bootstrap** 5.3.3 — MIT License — https://getbootstrap.com
* **Bootstrap Icons** 1.11.3 — MIT License — https://icons.getbootstrap.com

== Video ==

* Hero placeholder video — "4K 25 Ultra HD Free Drone Stock Footage"
  Source: https://archive.org/details/2-4-k-25-ultra-hd-free-drone-stock-footage
  License: Creative Commons Attribution 4.0 International — https://creativecommons.org/licenses/by/4.0/

== Privacy Policy ==

GrayFox AI Assistant collects and stores the following data from site visitors who use the chat widget:

* **Visitor messages** — stored in the `wp_grayfox_messages` table in your WordPress database.
* **Session ID** — a random identifier used to group messages into a conversation, stored in the visitor's browser session.
* **Visitor name and email** — stored in `wp_grayfox_conversations` only if the visitor voluntarily provides this information during a chat.
* **IP address (hashed)** — used for rate limiting only; stored as a transient, not written to a permanent table.

**What is sent to external services:** Visitor messages and relevant sections from your knowledge base are sent to the LLM API provider you configure in Settings (OpenAI, Anthropic, Google Gemini, or Groq). No visitor data is sent to GrayFox servers.

**Data retention:** Conversation data remains in your database until deleted. You can delete individual conversations from GrayFox > Conversations, or drop all tables by uninstalling the plugin.

Site administrators should disclose chatbot data collection to visitors in their site's own privacy policy.

== Source Code ==

This plugin's JavaScript source files are minified in the distributed zip. The full source code is available at:
https://github.com/breinosa-star/wpfox-assistant-plugin

== Changelog ==

= 1.0.0 =
* Initial release.
