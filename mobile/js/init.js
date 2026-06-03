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
 * PlayerGroup mobile app provider registration.
 *
 * Registers the PlayerGroupProvider (WS wrapper) and a deep-link handler.
 * The last evaluated expression is the exported object — its properties are
 * injected into `this` in all handler JavaScript (courseview.js).
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-disable */

const context = this;

/**
 * Wraps all PlayerGroup web service calls.
 */
class AddonModPlayerGroupProvider {

    getActivityData(cmid, siteId) {
        return context.CoreSitesProvider.getSite(siteId).then((site) => {
            return site.read('mod_playergroup_get_activity_data', {cmid}, {getFromCache: false});
        });
    }

    createGroup(cmid, name, description, badge, privacy, password, siteId) {
        return context.CoreSitesProvider.getSite(siteId).then((site) => {
            return site.write('mod_playergroup_create_group', {
                cmid, name, description, badge, privacy, password,
            });
        });
    }

    joinGroup(cmid, groupid, password, siteId) {
        return context.CoreSitesProvider.getSite(siteId).then((site) => {
            return site.write('mod_playergroup_join_group', {
                cmid, groupid, password: password || '',
            });
        });
    }

    leaveGroup(cmid, siteId) {
        return context.CoreSitesProvider.getSite(siteId).then((site) => {
            return site.write('mod_playergroup_leave_group', {cmid});
        });
    }

    editGroup(cmid, groupid, name, description, badge, privacy, password, siteId) {
        return context.CoreSitesProvider.getSite(siteId).then((site) => {
            return site.write('mod_playergroup_edit_group', {
                cmid, groupid, name, description, badge, privacy, password: password || '',
            });
        });
    }

    sendInvite(cmid, receiverid, siteId) {
        return context.CoreSitesProvider.getSite(siteId).then((site) => {
            return site.write('mod_playergroup_send_invite', {cmid, receiverid});
        });
    }

    acceptInvite(inviteid, siteId) {
        return context.CoreSitesProvider.getSite(siteId).then((site) => {
            return site.write('mod_playergroup_accept_invite', {inviteid});
        });
    }

    rejectInvite(inviteid, siteId) {
        return context.CoreSitesProvider.getSite(siteId).then((site) => {
            return site.write('mod_playergroup_reject_invite', {inviteid});
        });
    }
}

/**
 * Handles deep links to mod_playergroup activities.
 */
class AddonModPlayerGroupLinkHandler extends context.CoreContentLinksModuleIndexHandler {

    constructor() {
        super('AddonModPlayerGroup', 'playergroup');
        this.name = 'AddonModPlayerGroupLinkHandler';
    }
}

const playerGroupProvider = new AddonModPlayerGroupProvider();

context.CoreContentLinksDelegate.registerHandler(new AddonModPlayerGroupLinkHandler());

const result = {
    playerGroupProvider,
};

result;
