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
 * AMD module: handles send/accept/reject invite interactions.
 *
 * @module     mod_playergroup/invites
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/str'
], function($, Ajax, Notification, Str) {

    return {
        /**
         * Initialises invite-related event listeners.
         *
         * @param {number} cmid Course module ID.
         */
        init: function(cmid) {

            // Open the invite-colleagues modal.
            $(document).on('click', '[data-action="openinvites"]', function(e) {
                e.preventDefault();
                var modal = document.getElementById('pg-invite-modal');
                if (!modal) {
                    return;
                }
                $(modal).appendTo('body').modal('show');
            });

            // Send an invite: replace the button with a "sent" badge instead of reloading.
            $(document).on('click', '.pg-btn-sendinvite', function() {
                var btn = $(this);
                var receiverid = parseInt(btn.data('receiverid'), 10);

                btn.prop('disabled', true);

                Ajax.call([{
                    methodname: 'mod_playergroup_send_invite',
                    args: {cmid: cmid, receiverid: receiverid}
                }])[0].done(function() {
                    Str.get_string('invitesent', 'mod_playergroup').done(function(label) {
                        btn.replaceWith(
                            $('<span class="badge bg-success">' +
                                '<i class="fa fa-check me-1" aria-hidden="true"></i>' +
                                label +
                              '</span>')
                        );
                    }).fail(Notification.exception);
                }).fail(function(ex) {
                    btn.prop('disabled', false);
                    Notification.exception(ex);
                });
            });

            // Accept a received invite.
            $(document).on('click', '.pg-btn-acceptinvite', function() {
                var btn = $(this);
                var inviteid = parseInt(btn.data('inviteid'), 10);

                btn.prop('disabled', true);
                btn.siblings('.pg-btn-rejectinvite').prop('disabled', true);

                Ajax.call([{
                    methodname: 'mod_playergroup_accept_invite',
                    args: {inviteid: inviteid}
                }])[0].done(function() {
                    window.location.reload();
                }).fail(function(ex) {
                    btn.prop('disabled', false);
                    btn.siblings('.pg-btn-rejectinvite').prop('disabled', false);
                    Notification.exception(ex);
                });
            });

            // Reject a received invite.
            $(document).on('click', '.pg-btn-rejectinvite', function() {
                var btn = $(this);
                var inviteid = parseInt(btn.data('inviteid'), 10);

                btn.prop('disabled', true);
                btn.siblings('.pg-btn-acceptinvite').prop('disabled', true);

                Ajax.call([{
                    methodname: 'mod_playergroup_reject_invite',
                    args: {inviteid: inviteid}
                }])[0].done(function() {
                    window.location.reload();
                }).fail(function(ex) {
                    btn.prop('disabled', false);
                    btn.siblings('.pg-btn-acceptinvite').prop('disabled', false);
                    Notification.exception(ex);
                });
            });
        }
    };
});
