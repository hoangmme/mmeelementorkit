=== MMe Elementor Kit ===
Contributors: mmevn
Plugin URI: https://mme.vn
Tags: elementor, templates, template library, acf, scf
Requires at least: 5.5
Requires PHP: 7.2
Stable tag: 1.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Bộ công cụ Elementor của MMe.vn: chia sẻ template Master → Client và widget động (ACF/SCF).

== Description ==

* Master (Server): chỉ mở khi có mã kích hoạt do MMe.vn cấp cho đúng tên miền. Tạo secret key, chọn template / danh mục được chia sẻ và giới hạn số site cho mỗi key.
* Client: kết nối tới site Master bằng URL + secret key, chèn template từ tab "MMe Library" trong thư viện Elementor.
* MMe Widgets: "Map (no API key)" — bản đồ Google từ địa chỉ, toạ độ, link Google Maps (kể cả maps.app.goo.gl) hoặc field ACF Google Map / Link.
* MMe Widgets: "Info Table (ACF)" — bảng thông tin label / giá trị lấy từ field ACF/SCF, post meta, shortcode hoặc dynamic tag.

Site Master và Client đều cần cài plugin này cùng Elementor.

== Credits ==

Phát triển dựa trên "Elements Share for Elementor" của DivDojo (GPL-2.0+).

== Changelog ==

= 1.3.0 =
* New: "Map (no API key)" widget — Google Maps embed from an address, "lat,lng", a Google Maps link (maps.app.goo.gl short links are resolved and cached) or an ACF Google Map / Link field. Zoom, map type, language, height, grayscale and an "Open in Google Maps" link.
* Changed: widgets share one field-source helper (field picker, "Read fields from", editor preview post ID).

= 1.2.0 =
* New: Updates page — the plugin updates itself from github.com/hoangmme/mmeelementorkit (branch main) through the normal WordPress update screen.
* New: Widgets page listing every MMe widget with an on / off switch and an environment check (Elementor, Elementor Pro, ACF / SCF).

= 1.1.0 =
* Đổi tên thành MMe Elementor Kit (MMe.vn).
* New: separate Master (Server) and Client admin pages with a redesigned UI.
* New: Master dashboard stats, copyable site URL / keys, per-key usage bar and "reset usage".
* New: Client connection status, connection test and preview of the templates shared by the master.
* New: "Info Table (ACF)" Elementor widget (MMe Widgets category) — label/value rows from ACF/SCF fields, post meta, shortcodes or dynamic tags.
* New: Export / Import secret keys (JSON) to move the master to another site; templates and categories are re-matched by slug or title.
* Changed: REST API namespace is now `wp-json/mme-kit/v1` — master and client sites must both run 1.1.0+.
* Security: Master mode requires an activation code signed by MMe.vn (Ed25519, bound to the site domain). Without it there is no Master page, no key management and no template API.
* Security: a key can only download templates inside its own scope (template/{id} and template-json/{id}).
* Security: nonce and capability checks on all admin ajax actions.
* Fix: a key set to "Specific templates" with no template selected no longer exposes every template.
