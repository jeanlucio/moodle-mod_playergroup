// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Main entry point: handles filter, join, leave, edit and delegates create to creategroup module.
 *
 * @module     mod_playergroup/main
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/str',
    'core/modal',
    'core/modal_save_cancel',
    'core/modal_events',
    'core/templates',
    'core/togglesensitive',
    'mod_playergroup/creategroup'
], function($, Ajax, Notification, Str, Modal, ModalSaveCancel, ModalEvents, Templates, ToggleSensitive, CreateGroup) {

    /**
     * Calls mod_playergroup_join_group and reloads on success.
     *
     * @param {number} cmid
     * @param {number} groupid
     * @param {string} password
     */
    var joinGroup = function(cmid, groupid, password) {
        Ajax.call([{
            methodname: 'mod_playergroup_join_group',
            args: {cmid: cmid, groupid: groupid, password: password}
        }])[0].done(function() {
            window.location.reload();
        }).fail(function(error) {
            Notification.alert(Str.get_string('error', 'core'), error.message);
        });
    };

    /**
     * Initialises the show/hide toggle on a password field once the modal body is ready.
     *
     * @param {Modal} modal
     * @param {string} fieldid
     */
    var initPasswordToggle = function(modal, fieldid) {
        modal.getBodyPromise().then(function() {
            ToggleSensitive.init(fieldid);
            return;
        }).catch(Notification.exception);
    };

    /**
     * Opens the edit-group modal pre-populated with current values.
     *
     * @param {number} cmid
     * @param {number} groupid
     * @param {string} name
     * @param {string} description
     * @param {string} badge
     * @param {number} privacy
     */
    var editGroup = function(cmid, groupid, name, description, badge, privacy) {
        var titlePromise = Str.get_string('editgroup', 'mod_playergroup');
        var bodyPromise = Templates.render('mod_playergroup/modal_editgroup', {
            name: name,
            description: description,
            badge: badge,
            isprivacyopen: privacy === 0,
            isprivacyprotected: privacy === 1,
            isprivacyclosed: privacy === 2
        });

        ModalSaveCancel.create({
            title: titlePromise,
            body: bodyPromise
        }).then(function(modal) {
            modal.show();

            initPasswordToggle(modal, 'pg-edit-grouppassword');

            modal.getRoot().on('change', '#pg-edit-groupprivacy', function() {
                var passwordField = modal.getRoot().find('#pg-edit-password-field');
                if ($(this).val() === '1') {
                    passwordField.removeClass('d-none');
                } else {
                    passwordField.addClass('d-none');
                    modal.getRoot().find('#pg-edit-grouppassword').val('');
                }
            });

            modal.getRoot().on(ModalEvents.save, function(saveEvent) {
                saveEvent.preventDefault();

                var newname = modal.getRoot().find('#pg-edit-groupname').val();
                if (!newname.trim()) {
                    modal.getRoot().find('#pg-edit-groupname').addClass('is-invalid');
                    return;
                }

                modal.hide();
                Ajax.call([{
                    methodname: 'mod_playergroup_edit_group',
                    args: {
                        cmid: cmid,
                        groupid: groupid,
                        name: newname,
                        description: modal.getRoot().find('#pg-edit-groupdesc').val(),
                        badge: modal.getRoot().find('#pg-edit-groupbadge').val(),
                        privacy: parseInt(modal.getRoot().find('#pg-edit-groupprivacy').val(), 10),
                        password: modal.getRoot().find('#pg-edit-grouppassword').val()
                    }
                }])[0].done(function() {
                    window.location.reload();
                }).fail(function(error) {
                    Notification.alert(Str.get_string('error', 'core'), error.message);
                });
            });

            modal.getRoot().on(ModalEvents.hidden, function() {
                modal.destroy();
            });

            return modal;
        }).catch(Notification.exception);
    };

    /**
     * Opens a read-only modal listing a group's members.
     *
     * @param {string} groupname
     * @param {Object[]} members Array of {fullname, isleader}.
     */
    var viewMembers = function(groupname, members) {
        var titlePromise = Str.get_string('groupmembers_named', 'mod_playergroup', groupname);
        var bodyPromise = Templates.render('mod_playergroup/members_list', {members: members});

        Modal.create({
            title: titlePromise,
            body: bodyPromise,
            show: true,
            removeOnClose: true
        }).catch(Notification.exception);
    };

    /**
     * Shows a password modal then calls joinGroup.
     *
     * @param {number} cmid
     * @param {number} groupid
     */
    var joinProtectedGroup = function(cmid, groupid) {
        var titlePromise = Str.get_string('joingroup', 'mod_playergroup');
        var bodyPromise = Templates.render('mod_playergroup/modal_joingroup', {});

        ModalSaveCancel.create({
            title: titlePromise,
            body: bodyPromise
        }).then(function(modal) {
            modal.show();

            initPasswordToggle(modal, 'pg-join-password');

            modal.getRoot().on(ModalEvents.save, function(saveEvent) {
                saveEvent.preventDefault();

                var password = modal.getRoot().find('#pg-join-password').val();
                if (!password.trim()) {
                    modal.getRoot().find('#pg-join-password').addClass('is-invalid');
                    return;
                }

                modal.hide();
                joinGroup(cmid, groupid, password);
            });

            modal.getRoot().on(ModalEvents.hidden, function() {
                modal.destroy();
            });

            return modal;
        }).catch(Notification.exception);
    };

    return {
        /**
         * Initialises all interactive behaviours on the student view.
         *
         * @param {number} cmid Course module ID.
         */
        init: function(cmid) {
            CreateGroup.init(cmid);

            // Filter full groups.
            $(document).on('change', '#pg-filter-full', function() {
                if ($(this).is(':checked')) {
                    $('.pg-group-card[data-full="true"]').hide();
                } else {
                    $('.pg-group-card').show();
                }
            });

            // Edit group button (creator only).
            $(document).on('click', '[data-action="editgroup"]', function(e) {
                e.preventDefault();
                var btn = $(this);
                editGroup(
                    cmid,
                    parseInt(btn.data('groupid'), 10),
                    btn.data('name'),
                    btn.data('description'),
                    btn.data('badge'),
                    parseInt(btn.data('privacy'), 10)
                );
            });

            // View members button.
            $(document).on('click', '[data-action="viewmembers"]', function() {
                var btn = $(this);
                viewMembers(btn.data('groupname'), btn.data('members') || []);
            });

            // Join group button.
            $(document).on('click', '.pg-btn-join', function() {
                var groupid = parseInt($(this).data('groupid'), 10);
                var privacy = parseInt($(this).data('privacy'), 10);

                if (privacy === 1) {
                    joinProtectedGroup(cmid, groupid);
                } else {
                    joinGroup(cmid, groupid, '');
                }
            });

            // Leave group button.
            $(document).on('click', '[data-action="leavegroup"]', function(e) {
                e.preventDefault();

                let leaveLabel = '';
                Str.get_strings([
                    {key: 'leavegroup', component: 'mod_playergroup'},
                    {key: 'leavegroupconfirm', component: 'mod_playergroup'}
                ]).then(function(strings) {
                    leaveLabel = strings[0];
                    return ModalSaveCancel.create({
                        title: strings[0],
                        body: strings[1]
                    });
                }).then(function(modal) {
                    modal.setSaveButtonText(leaveLabel);
                    modal.show();

                    modal.getRoot().on(ModalEvents.save, function() {
                        Ajax.call([{
                            methodname: 'mod_playergroup_leave_group',
                            args: {cmid: cmid}
                        }])[0].done(function() {
                            window.location.reload();
                        }).fail(function(error) {
                            Notification.alert(Str.get_string('error', 'core'), error.message);
                        });
                    });

                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });

                    return modal;
                }).catch(Notification.exception);
            });
        }
    };
});
