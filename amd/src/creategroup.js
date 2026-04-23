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
    'core/modal_factory',
    'core/modal_events',
    'core/templates',
    'core/str'
], function($, Ajax, Notification, ModalFactory, ModalEvents, Templates, Str) {
    return {
        init: function(cmid) {
            $('[data-action="creategroup"]').on('click', function(e) {
                e.preventDefault();

                var titlePromise = Str.get_string('creategroup', 'mod_playergroup');
                var bodyPromise = Templates.render('mod_playergroup/modal_creategroup', {});

                ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: titlePromise,
                    body: bodyPromise
                }).then(function(modal) {
                    modal.show();

                    // Handle the save button click.
                    modal.getRoot().on(ModalEvents.save, function(saveEvent) {
                        saveEvent.preventDefault();

                        var name = $('#groupname').val();
                        var desc = $('#groupdesc').val();
                        var badge = $('#groupbadge').val();

                        if (!name.trim()) {
                            $('#groupname').addClass('is-invalid');
                            return;
                        }

                        // Call the external function.
                        Ajax.call([{
                            methodname: 'mod_playergroup_create_group',
                            args: {
                                cmid: cmid,
                                name: name,
                                description: desc,
                                badge: badge
                            }
                        }])[0].done(function() {
                            modal.hide();
                            window.location.reload(); // Reloads to show the new group on screen.
                        }).fail(Notification.exception);
                    });

                    // Clean up DOM when hidden.
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });

                    return modal;
                }).catch(Notification.exception);
            });
        }
    };
});
