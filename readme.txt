=== 4L Feedback System ===
Contributors: theconcreteprotector
Tags: feedback, retrospective, 4ls, training, surveys
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A structured 4Ls retrospective tool (Loved, Loathed, Longed for, Learned) with email notifications, admin responses, and shortcodes.

== Description ==

The 4L Feedback System lets you collect structured feedback from users using the proven retrospective format: Loved / Loathed / Longed for / Learned. Submissions are stored in a custom database table ("breadcrumbs"), notifications are emailed to a configurable address, and administrators can respond to each submission either privately or publicly.

**Shortcodes**

* `[fourl_feedback_form]` — display the 4Ls feedback form on any page or post. Optionally renders the logged-in user's previous feedback inline (`show_breadcrumbs="yes"`).
* `[fourl_feedback_responses]` — lists the *logged-in user's* public admin responses.
* `[fourl_feedback_breadcrumbs]` — lists the *logged-in user's* submitted feedback (with public responses inline).

Both `responses` and `breadcrumbs` are scoped to the currently logged-in user — each user only sees the feedback they themselves submitted, and the responses given to them.

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
    title_label="DCU Course, Project, Event"
    title_placeholder="e.g. Graniflex Course"
    feedback_heading="Feedback"
    submit_label="Submit feedback"
    show_name="yes"
    show_email="yes"
    show_title="yes"
    show_breadcrumbs="yes"
    breadcrumbs_limit="10"]`

`[fourl_feedback_responses limit="10" show_title="yes"]`

`[fourl_feedback_breadcrumbs limit="20" status="" show_items="yes" show_name="yes"]`

== Changelog ==

= 1.1.0 =
* Submissions are now tied to the logged-in user. `[fourl_feedback_responses]` and `[fourl_feedback_breadcrumbs]` filter to only show that user's own data.
* Form: default `title_label` is now "DCU Course, Project, Event"; placeholder is "Graniflex Course"; a "Feedback" heading appears above the four quadrants.
* Form: new `show_breadcrumbs="yes"` attribute renders the user's previous feedback inline beneath the form.

= 1.0.0 =
* Initial release: form, AJAX submission, email notification, admin dashboard, responses, three shortcodes.
