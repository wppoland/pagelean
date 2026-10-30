=== Pagelean - Conditional Script and Style Loading ===
Contributors: motylanogha
Tags: performance, scripts, optimization, unload css, dequeue
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.2.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Dequeue individual scripts and styles on the pages that do not need them. Fewer requests, lighter pages, faster loads - using WordPress core.

== Description ==

Most plugins load their CSS and JavaScript on every page, even where their feature never appears. A contact form script on your checkout, a slider stylesheet on your blog posts, a social-share bundle on your privacy policy. That weight slows every visit.

Pagelean lets you remove a specific script or style from exactly the pages where it is not needed - front page, blog index, single posts and pages (by ID), single entries of a post type, the WooCommerce shop, product, cart and checkout pages, or by device. It uses the standard WordPress enqueue system (`wp_dequeue_script` / `wp_dequeue_style`), so there is no page-builder lock-in and nothing proprietary to learn.

= What it does =

* Remove any registered script or style handle by name.
* Choose *where* it is removed: everywhere, the front page, the blog posts index, single posts/pages (by ID), single entries of any public post type, the WooCommerce shop, product, cart or checkout page (when WooCommerce is active), or only mobile / only desktop.
* Test mode: the rules apply only to logged-in administrators while everyone else gets the untouched page, with a notice in the admin until you turn it off.
* Dequeue by default; tick "Also deregister" on a rule only when you want the handle gone even where another script depends on it.
* Autocomplete from the handles your front-end pages actually load, recorded while you browse the site as an administrator (up to 500, with their source file).
* Runs late on the front end so it removes handles reliably, and never touches the admin.

= What it does not do =

It does not minify, combine or defer. It removes what you tell it to remove - nothing automatic, nothing hidden. Removing a handle that another script depends on can break functionality, so change one rule at a time and check the front end.

= Part of the Plogins family =

Pagelean is one of a family of small, focused WordPress and WooCommerce plugins by WPPoland. Learn more at [plogins.com](https://plogins.com/).

== Installation ==

1. Upload the plugin to `/wp-content/plugins/pagelean`, or install it from the Plugins screen.
2. Activate it.
3. Go to **Pagelean** in the main admin menu.
4. Add a rule: type the handle, pick script or style, and choose where to remove it.
5. Save, then check the front end.

== Frequently Asked Questions ==

= How do I find a handle name? =

Visit a few pages of your site while logged in as an administrator. Pagelean records the scripts and styles each page loads and offers them as autocomplete in the Handle field, with the file each one loads. You can still look it up by hand: the `id` attribute of a `<script>` / `<link>` tag usually ends in `-js` or `-css`, and the handle is that value without the suffix.

= What does test mode do? =

With test mode on, the rules run only for logged-in users who can manage options (administrators). Every other visitor gets the page with all its scripts and styles. Check your pages, then turn test mode off to apply the rules to everyone. A notice in the admin reminds you while it is on.

= What is the difference between dequeue and "Also deregister"? =

Dequeue (the default) takes the handle off the page, but WordPress still prints it when another script on that page depends on it, so nothing breaks. "Also deregister" removes the handle from WordPress entirely for that request: it is gone even as a dependency, and every script that depends on it is dropped too. Use it only when you know nothing on the page needs the handle.

= When do the Post/Page IDs apply? =

Only to the two single posts/pages conditions. The other conditions (everywhere, front page, blog index, mobile, desktop) decide from the request alone, so the IDs box is greyed out for them and a rule set that way removes the handle on every page it matches.

= Will this break my site? =

It only removes what you configure. A dequeued handle that another script depends on is still printed for that script; with "Also deregister" ticked, scripts that depend on it are dropped as well. Use test mode to check the result before visitors see it. Change one rule at a time and verify the front end. You can remove a rule to restore the asset instantly.

= Does it work without a page builder or WooCommerce? =

Yes. It uses WordPress core functions only and works on any theme.

= Does it run in the admin? =

No. Rules apply on the front end only.

== Screenshots ==

1. The rule table: remove a handle on the pages that do not need it.

== Changelog ==

= 1.2.2 =
* Fixed: deleting the plugin now removes its settings, rules and the list of seen script and style handles, on every site of a multisite network. While an add-on that stores rules in the same settings is installed, those settings stay.

= 1.2.1 =
* Links point at the new plogins.com address.

= 1.2.0 =
* Test mode: a new setting applies the rules only to logged-in administrators, while everyone else gets the untouched page. An admin notice shows while it is on.
* Behaviour change: a rule now only dequeues its handle. Until 1.1.2 every rule also deregistered it, which silently dropped every script that depends on that handle. A new per-rule "Also deregister" box keeps the old behaviour, and rules saved before this update have it ticked automatically, so they keep working exactly as before. New rules start on dequeue only.
* New conditions: single entries of a chosen public post type, and the WooCommerce shop, product, cart and checkout pages (offered while WooCommerce is active).
* Handle autocomplete now lists the scripts and styles your front-end pages actually load, recorded while you browse the site as an administrator (up to 500, with their source file). Before, it only knew the handles registered on the admin screen.

= 1.1.2 =
* The settings form sanitises every rule field as it is read, and the saved notice only shows for the exact value the plugin sets.

= 1.1.1 =
* Renamed to Pagelean, a plain English name in place of the Esperanto one. The text domain and the plugin folder follow the name; the stored settings, options and every hook are unchanged.
* The autoloader no longer leaves a global variable behind.

= 1.1.0 =
* Renamed to Malpezo. The WordPress.org review team asks a plugin name to lead with a distinctive, coined identifier rather than a generic descriptive word. Malpezo is Esperanto for lightness. The text domain follows the name; the stored rules, the settings and every hook are unchanged.

= 1.0.5 =
* The translation template was regenerated. It still named an older version of the plugin and pointed at source lines that had since moved, which is what translation tools read to show a string in context.

= 1.0.4 =
* Tested against WordPress 7.1. Verified by activating this build on a clean 7.1 install with WooCommerce 11.1, not by editing the header.

= 1.0.3 =
* The Post/Page IDs box is now available only for the two single posts/pages conditions, the ones that can act on it. With any other condition it is greyed out with a short note, so a rule set to Everywhere or to a device can no longer look as if it were limited to a few pages while the handle is in fact removed across the whole site.

= 1.0.1 =
* Version bump to meet the family release floor for the WordPress.org submission.

= 1.0.0 =
* Initial release.
