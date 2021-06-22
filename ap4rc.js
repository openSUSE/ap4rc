/**
 * Application Passwords handling for roundcube
 *
 * @author darix & jdsn
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this page.
 *
 * Copyright (C) 2021 SUSE LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this page.
 */

window.rcmail && rcmail.addEventListener('init', function(evt) {
    if (!rcmail.env.application_passwords) {
        rcmail.env.application_passwords = {};
    }

    $('#ap4rc-prop-save-button').on('click', null, function(e) {
        var lock, data, form = $('#ap4rc-prop-save'),
            application_name = form.find('input[name="new_application_name"]');

        if (application_name.length && !application_name.val().length) {
            alert(rcmail.get_label('missingapplicationname','ap4rc'));
            application_name.select();
            return false;
        }

    })

    $('#ap4rc-applications tbody .button.delete').on('click', null, function(e) {
        var id = $(this).attr('rel');
        var lock = rcmail.set_busy(true, 'saving');
        if (rcmail.http_post('plugin.ap4rc-delete', { remove_id: id }, lock)) {
           $(this).parent().parent().remove();
        }
    });

    function tag_copy_divs() {
        var copy = document.querySelectorAll("#new_password");

        for (const copied of copy) {
            copied.onclick = function() {
                document.execCommand("copy");
            };
            copied.addEventListener("copy", function(event) {
                event.preventDefault();
                if (event.clipboardData) {
                    event.clipboardData.setData("text/plain", copied.textContent);
                };
            });
        };
    }
   tag_copy_divs();
})
