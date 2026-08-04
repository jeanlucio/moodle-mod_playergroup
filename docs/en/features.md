# ✨ Features

* 👥 **Group Creation:** Students create groups with a custom name, description, and emoji badge — no teacher intervention needed.
* 🔐 **Privacy Levels:** Open, protected (password), and closed (invite only).
* 📨 **Invite System:** Peer invitations via Moodle's native notification (bell + email for offline users); the invite picker is gated by `moodle/course:viewparticipants` and filtered to actively enrolled students, matching the same visibility rules as the rest of the course.
* 👀 **View Members:** Every group card's member count is a button that opens a read-only list of that group's members, with the founder marked as leader — on both the web view and the mobile app.
* ⚙️ **Configurable Limits:** Teachers set minimum and maximum members per group.
* 🗂️ **Automatic Grouping:** A Moodle grouping is created automatically — no manual setup required. Deleting the activity only ever removes the groups and grouping it actually created; if the teacher pointed it at a pre-existing grouping instead, that grouping and any groups not registered by this activity are left untouched.
* 🔒 **Concurrency-Safe:** Creating, joining, or accepting an invite into a group is serialised per activity instance, so two near-simultaneous requests (a double-click, or two devices) can never place the same student in two groups at once or push a group past its member limit.
* 🏆 **Gradebook Integration:** Grade awarded automatically when a student joins or creates a group; permanent even if the student later leaves.
* ✅ **Activity Completion:** Custom rule — student must join or create a group.
* 📊 **Teacher Reports:**
  * **Activity Log:** Audit view of the last 200 events (group created, joined, left, invite accepted), with CSV and Excel export.
  * **Groups and Members:** One row per member — group, privacy, role (leader/member), and name — for a full class roster at a glance, also exportable to CSV and Excel.
* 🔗 **Groups API:** Full integration with Moodle's native groups and groupings, plus a public read-only API (`mod_playergroup\api\group_info`) other plugins can query — a single student's group summary, or badges for several groups in one bulk call.
* 📱 **Mobile App:** Native support in the official Moodle app — create, join, leave, invite, view members, and manage your group on the go.
