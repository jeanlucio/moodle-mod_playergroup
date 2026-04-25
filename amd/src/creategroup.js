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
 * Handles the creation of groups via AJAX.
 *
 * @module     mod_playergroup/creategroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/modal_save_cancel',
    'core/modal_events',
    'core/templates',
    'core/str'
], function($, Ajax, Notification, ModalSaveCancel, ModalEvents, Templates, Str) {
    return {
        init: function(cmid) {
            $(document).on('click', '[data-action="creategroup"]', function(e) {
                e.preventDefault();

                var titlePromise = Str.get_string('creategroup', 'mod_playergroup');
                var bodyPromise = Templates.render('mod_playergroup/modal_creategroup', {});

                ModalSaveCancel.create({
                    title: titlePromise,
                    body: bodyPromise
                }).then(function(modal) {
                    modal.show();

                    // Toggle password field visibility based on privacy selection.
                    modal.getRoot().on('change', '#pg-groupprivacy', function() {
                        var passwordField = modal.getRoot().find('#pg-password-field');
                        if ($(this).val() === '1') {
                            passwordField.removeClass('d-none');
                        } else {
                            passwordField.addClass('d-none');
                            modal.getRoot().find('#pg-grouppassword').val('');
                        }
                    });

                    modal.getRoot().on(ModalEvents.save, function(saveEvent) {
                        saveEvent.preventDefault();

                        var name = modal.getRoot().find('#pg-groupname').val();
                        var desc = modal.getRoot().find('#pg-groupdesc').val();
                        var badge = modal.getRoot().find('#pg-groupbadge').val();
                        var privacy = parseInt(modal.getRoot().find('#pg-groupprivacy').val(), 10);
                        var password = modal.getRoot().find('#pg-grouppassword').val();

                        if (!name.trim()) {
                            modal.getRoot().find('#pg-groupname').addClass('is-invalid');
                            return;
                        }

                        Ajax.call([{
                            methodname: 'mod_playergroup_create_group',
                            args: {
                                cmid: cmid,
                                name: name,
                                description: desc,
                                badge: badge,
                                privacy: privacy,
                                password: password
                            }
                        }])[0].done(function() {
                            modal.hide();
                            window.location.reload();
                        }).fail(Notification.exception);
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
