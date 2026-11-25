=== BlockWarden: Stop Fake Accounts and Login Attacks for WooCommerce ===
Contributors: iqbal511138
Tags: woocommerce, spam registration, login security, brute force, fake accounts
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stops bot sign-ups, password guessing and reset floods on WooCommerce My Account with an invisible pass. No CAPTCHA. Cleans up fake customer accounts.

== Description ==

Bots love WooCommerce My Account pages. They create thousands of fake customer accounts, guess passwords, and flood customers with password reset emails. The usual fix is a CAPTCHA, which real customers hate and which costs you sales.

BlockWarden works differently. When a real person opens My Account, their browser quietly receives a signed pass. Bots that post straight to the form, which is how almost all of them work, never have one and are turned away. Nobody has to click on traffic lights.

= What it does =

* **Invisible pass** on the WooCommerce registration, login and lost password forms. Each form only starts blocking after the first real visitor has used it, so a caching or theme problem can never lock out your customers.
* **Login lockout.** After a few failed logins from one address, logins from it are refused for a while. Works on My Account, wp-login.php and XML-RPC.
* **Sign-up limit.** At most a few new accounts per hour from one address, on My Account and at checkout.
* **Temporary email block.** A built-in list of throwaway inbox services, plus your own block and allow lists.
* **Password reset limit** to stop reset email floods.
* **Fake account cleanup.** Finds customer accounts that never ordered and were never active after signing up, shows you how many and a sample, and deletes them in small background batches after you confirm.
* **Clear dashboard** with what was stopped and why.

BlockWarden focuses on WooCommerce. It is not a general comment spam or firewall plugin and works alongside them.

= Privacy =

Visitor addresses are stored only as keyed fingerprints. BlockWarden makes no external requests and uses no third-party service.

== Installation ==

1. Install and activate BlockWarden. WooCommerce must be active.
2. Visit your My Account page once while logged out and log in. That arms the login form.
3. Open BlockWarden in the admin menu to see each form's status.

== Frequently Asked Questions ==

= Will customers notice anything? =

No. The pass is fetched in the background. If something blocks it (for example a page cached for a very long time), the form keeps working until a real visitor brings a valid pass.

= I locked myself out. =

Wait for the lockout to end (15 minutes by default) or reset your password. Developers can change how the visitor address is read with the `blockwarden_client_ip` filter.

= Does it protect the block checkout? =

Sign-up limits and the temporary email block apply to accounts created at checkout. For card testing and fake orders at checkout, use our OrderWarden plugin.

= Is account cleanup safe? =

It only touches users whose only role is Customer, who have no orders, signed up longer ago than you choose, and were not active since. You see the count and a sample first, and must confirm. Deleted accounts cannot be restored, so take a backup first.

== Screenshots ==

1. Overview with the status of each form.
2. Settings.
3. Account cleanup.

== Changelog ==

= 1.0.0 =
* First release: invisible pass, login lockout, sign-up limit, temporary email block, password reset limit, fake account cleanup.

== Upgrade Notice ==

= 1.0.0 =
First release.
