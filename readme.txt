=== Edel Chat Pro ===
Contributors: edelhearts
Tags: chat, community, group chat, shortcode, communication
Requires at least: 5.7
Tested up to: 6.9
Stable tag: 1.2.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A powerful, LINE-style group chat plugin accessible via shortcode.
Supports images (Drag & Drop), stamps, reactions, and advanced moderation tools.

== Description ==

Edel Chat Pro is a feature-rich chat plugin that allows you to turn any post or page into a real-time chat room simply by pasting a shortcode.
It is designed for community building, event engagement, and customer support.

**Key Features:**

* **Real-time Communication:** Messages update automatically via Ajax.
* **Multiple Rooms:** Each post/page has its own independent chat room.
* **Rich Media:** Send text, stamps, and images.
* **Modern UX:**
    * **Drag & Drop Image Upload:** Drag images directly into the chat area.
    * **Paste to Upload:** Paste images from your clipboard directly into the text area.
    * **Link Preview:** Automatically generates OGP cards for URLs.
* **Social Features:**
    * **Reactions:** React to messages with emojis.
    * **Replies:** Reply to specific messages.
    * **Edit/Delete:** Users can edit or delete their own messages.
* **User Profiles:** Front-end profile editing and avatar uploads (saved in a custom directory).
* **Server Friendly:**
    * **Smart Polling:** Automatically reduces polling frequency when the tab is inactive to save server resources.
    * **Adjustable Interval:** Admins can set the polling interval.
* **Powerful Moderation:**
    * **IP Ban:** Block specific IP addresses from posting.
    * **Spam Protection:** Set limits on consecutive posts.
    * **NG Words:** Filter prohibited words.
    * **Log Management:** View, delete individual messages, or clear entire rooms from the admin panel.
    * **CSV Export:** Download chat logs for backup or analysis.

== Installation ==

1.  Upload the `edel-chat-pro` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Edel Chat Pro > Settings** to configure colors, banned IPs, and other options.
4.  Add the shortcode `[edel_chat]` to any post or page to display the chat.
5.  (Optional) Add `[edel_mypage]` to a page to allow users to edit their profiles.

== Frequently Asked Questions ==

= How do I add a chat room? =
Simply add the `[edel_chat]` shortcode to the content of any Post or Page.
Each page will function as a separate room.

= Can I customize the colors? =
Yes, you can change the background, user bubbles, and other user bubbles colors from the settings page.

= How does the IP Ban work? =
Go to the settings page and enter the IP addresses you want to block in the "Banned IP List" field (one per line).

= Where are user avatars stored? =
User avatars uploaded via the My Page feature are stored in `wp-content/uploads/edel-avatars/` to keep your media library clean.

== Screenshots ==

1.  **Chat Interface:** Clean, LINE-style interface with reactions and stamps.
2.  **Image Upload:** Confirm modal when dragging & dropping images.
3.  **Admin Settings:** Easy configuration for design and moderation.
4.  **Log Management:** View logs, download CSV, and ban users.

== Changelog ==

= 1.2.0 =
* Added Internationalization (i18n) support.
* Added "Settings" link to the plugin action links.
* Updated admin menu structure.
* Fixed coding standards and security issues.

= 1.1.0 =
* Added Image Drag & Drop and Paste support with confirmation modal.
* Added Smart Polling (reduces server load when tab is inactive).

= 1.0.0 =
* Initial Pro release with stamps, reactions, and OGP support.