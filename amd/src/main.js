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
 * Main entry point: handles filter, join, leave and delegates create to creategroup module.
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
    'core/modal_factory',
    'core/modal_events',
    'core/templates',
    'mod_playergroup/creategroup'
], function($, Ajax, Notification, Str, ModalFactory, ModalEvents, Templates, CreateGroup) {

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
        }).fail(Notification.exception);
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

        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: titlePromise,
            body: bodyPromise
        }).then(function(modal) {
            modal.show();

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

                Str.get_string('leavegroupconfirm', 'mod_playergroup').then(function(confirmMsg) {
                    // eslint-disable-next-line no-alert
                    if (window.confirm(confirmMsg)) {
                        Ajax.call([{
                            methodname: 'mod_playergroup_leave_group',
                            args: {cmid: cmid}
                        }])[0].done(function() {
                            window.location.reload();
                        }).fail(Notification.exception);
                    }
                    return true;
                }).catch(Notification.exception);
            });
        }
    };
});
