// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * PlayerGroup mobile app course view handlers.
 *
 * `this` is the Angular component scope. `CONTENT_OTHERDATA` values are
 * auto-parsed by the Moodle App: JSON strings become JS objects/arrays.
 * Never call JSON.parse() on them.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-disable */

// ---------------------------------------------------------------------------
// State initialisation — the app has already parsed the JSON in otherdata.
// ---------------------------------------------------------------------------

const cmid = parseInt(this.CONTENT_OTHERDATA.cmid, 10);

// These are already JavaScript arrays (auto-parsed from the JSON strings).
this.groups = this.CONTENT_OTHERDATA.groups || [];
this.receivedInvites = this.CONTENT_OTHERDATA.receivedinvites || [];
this.inviteableUsers = this.CONTENT_OTHERDATA.inviteableusers || [];

this.showCreateForm = false;
this.showEditForm = false;
this.showInvitePanel = false;
this.showMembersPanel = false;
this.selectedGroup = null;

this.createData = {name: '', description: '', badge: '🛡️', privacy: 0, password: ''};
this.editData = {groupid: 0, name: '', description: '', badge: '', privacy: 0, password: ''};

// Signal to core-loading that the view is ready.
this.loaded = true;

// ---------------------------------------------------------------------------
// Panel helpers
// ---------------------------------------------------------------------------

this.showCreate = () => {
    this.createData = {name: '', description: '', badge: '🛡️', privacy: 0, password: ''};
    this.showCreateForm = true;
    this.showEditForm = false;
    this.showInvitePanel = false;
    this.showMembersPanel = false;
};

this.showEdit = (group) => {
    this.editData = {
        groupid: group.groupid,
        name: group.rawname,
        description: group.rawdescription,
        badge: group.badge,
        privacy: group.privacy,
        password: '',
    };
    this.showEditForm = true;
    this.showCreateForm = false;
    this.showInvitePanel = false;
    this.showMembersPanel = false;
};

this.showInvite = () => {
    this.showInvitePanel = true;
    this.showCreateForm = false;
    this.showEditForm = false;
    this.showMembersPanel = false;
};

this.showMembers = (group) => {
    this.selectedGroup = group;
    this.showMembersPanel = true;
    this.showCreateForm = false;
    this.showEditForm = false;
    this.showInvitePanel = false;
};

this.cancelForm = () => {
    this.showCreateForm = false;
    this.showEditForm = false;
    this.showInvitePanel = false;
    this.showMembersPanel = false;
};

// ---------------------------------------------------------------------------
// Group actions
// ---------------------------------------------------------------------------

this.createGroup = () => {
    const data = this.createData;

    if (!data.name || !data.name.trim()) {
        this.CoreDomUtilsProvider.showErrorModal(
            this.CONTENT_OTHERDATA.str_groupname_required
        );
        return;
    }

    this.CoreDomUtilsProvider.showModalLoading().then((modal) => {
        return this.playerGroupProvider.createGroup(
            cmid,
            data.name.trim(),
            data.description || '',
            data.badge || '🛡️',
            data.privacy || 0,
            data.privacy === 1 ? (data.password || '') : '',
            null
        ).then(() => {
            modal.dismiss();
            this.cancelForm();
            this.refreshContent(false);
        }).catch((err) => {
            modal.dismiss();
            this.CoreDomUtilsProvider.showErrorModalDefault(
                err, this.CONTENT_OTHERDATA.str_error
            );
        });
    });
};

this.editGroup = () => {
    const data = this.editData;

    if (!data.name || !data.name.trim()) {
        this.CoreDomUtilsProvider.showErrorModal(
            this.CONTENT_OTHERDATA.str_groupname_required
        );
        return;
    }

    this.CoreDomUtilsProvider.showModalLoading().then((modal) => {
        return this.playerGroupProvider.editGroup(
            cmid,
            data.groupid,
            data.name.trim(),
            data.description || '',
            data.badge || '🛡️',
            data.privacy || 0,
            data.privacy === 1 ? (data.password || '') : '',
            null
        ).then(() => {
            modal.dismiss();
            this.cancelForm();
            this.refreshContent(false);
        }).catch((err) => {
            modal.dismiss();
            this.CoreDomUtilsProvider.showErrorModalDefault(
                err, this.CONTENT_OTHERDATA.str_error
            );
        });
    });
};

this.joinGroup = (group) => {
    const doJoin = (password) => {
        this.CoreDomUtilsProvider.showModalLoading().then((modal) => {
            return this.playerGroupProvider.joinGroup(cmid, group.groupid, password || '', null)
                .then(() => {
                    modal.dismiss();
                    this.refreshContent(false);
                }).catch((err) => {
                    modal.dismiss();
                    this.CoreDomUtilsProvider.showErrorModalDefault(
                        err, this.CONTENT_OTHERDATA.str_error
                    );
                });
        });
    };

    if (group.isprivacyprotected) {
        this.CoreDomUtilsProvider.showPrompt(
            this.CONTENT_OTHERDATA.str_enterpassword,
            this.CONTENT_OTHERDATA.str_joinprotected,
            this.CONTENT_OTHERDATA.str_joinopen,
            'password'
        ).then((password) => {
            if (password !== undefined) {
                doJoin(password);
            }
        });
    } else {
        doJoin('');
    }
};

this.leaveGroup = () => {
    this.CoreDomUtilsProvider.showConfirm(
        this.CONTENT_OTHERDATA.str_leaveconfirm
    ).then(() => {
        this.CoreDomUtilsProvider.showModalLoading().then((modal) => {
            return this.playerGroupProvider.leaveGroup(cmid, null)
                .then(() => {
                    modal.dismiss();
                    this.refreshContent(false);
                }).catch((err) => {
                    modal.dismiss();
                    this.CoreDomUtilsProvider.showErrorModalDefault(
                        err, this.CONTENT_OTHERDATA.str_error
                    );
                });
        });
    }).catch(() => {
        // User cancelled.
    });
};

// ---------------------------------------------------------------------------
// Invite actions
// ---------------------------------------------------------------------------

this.acceptInvite = (inviteid) => {
    this.CoreDomUtilsProvider.showModalLoading().then((modal) => {
        return this.playerGroupProvider.acceptInvite(inviteid, null)
            .then(() => {
                modal.dismiss();
                this.refreshContent(false);
            }).catch((err) => {
                modal.dismiss();
                this.CoreDomUtilsProvider.showErrorModalDefault(
                    err, this.CONTENT_OTHERDATA.str_error
                );
            });
    });
};

this.rejectInvite = (inviteid) => {
    this.CoreDomUtilsProvider.showModalLoading().then((modal) => {
        return this.playerGroupProvider.rejectInvite(inviteid, null)
            .then(() => {
                modal.dismiss();
                this.refreshContent(false);
            }).catch((err) => {
                modal.dismiss();
                this.CoreDomUtilsProvider.showErrorModalDefault(
                    err, this.CONTENT_OTHERDATA.str_error
                );
            });
    });
};

this.sendInvite = (userid) => {
    this.CoreDomUtilsProvider.showModalLoading().then((modal) => {
        return this.playerGroupProvider.sendInvite(cmid, userid, null)
            .then(() => {
                modal.dismiss();
                this.refreshContent(false);
            }).catch((err) => {
                modal.dismiss();
                this.CoreDomUtilsProvider.showErrorModalDefault(
                    err, this.CONTENT_OTHERDATA.str_error
                );
            });
    });
};

// ---------------------------------------------------------------------------
// Pull-to-refresh
// ---------------------------------------------------------------------------

this.doRefresh = (done) => {
    this.refreshContent(false);
    done && done();
};
