=== 4L Feedback System ===
Contributors: theconcreteprotector
Tags: feedback, retrospective, 4ls, training, surveys
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A structured 4Ls retrospective tool (Loved, Loathed, Longed for, Learned) with email notifications, admin responses, and shortcodes.

== Description ==

The 4L Feedback System lets you collect structured feedback from users using the proven retrospective format: Loved / Loathed / Longed for / Learned. Submissions are stored in a custom database table ("breadcrumbs"), notifications are emailed to a configurable address, and administrators can respond to each submission either privately or publicly.

**Shortcodes**

* `[fourl_feedback_form]` — display the 4Ls feedback form on any page or post.
* `[fourl_feedback_responses]` — list the most recent public admin responses.
* `[fourl_feedback_breadcrumbs]` — list submitted feedback (with optional public responses inline).

**Admin features**

* Settings page to set the notification email and submitter requirements.
* Submissions list with status filters (New / Reviewed / Actioned / Archived).
* Single-submission view with all four quadrants and starred priorities.
* Add public or internal responses, change status, delete submissions.

== Installation ==

1. Upload the `4lfeedback` folder to `/wp-content/plugins/`, or install the zip via the WordPress plugin uploader.
2. Activate "4L Feedback System" in **Plugins**.
3. Go to **4L Feedback → Settings** to set the notification email.
4. Add `[fourl_feedback_form]` to any page where you want users to submit feedback.

== Shortcode reference ==

`[fourl_feedback_form
    title_label="Project, sprint, or training"
    submit_label="Submit feedback"
    show_name="yes"
    show_email="yes"
    show_title="yes"]`

`[fourl_feedback_responses limit="10" show_title="yes"]`

`[fourl_feedback_breadcrumbs limit="20" status="" show_items="yes" show_name="yes"]`

== Changelog ==

= 1.0.0 =
* Initial release: form, AJAX submission, email notification, admin dashboard, responses, three shortcodes.
