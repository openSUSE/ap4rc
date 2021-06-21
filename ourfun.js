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

    function render() {
        var table = $('#ourfun-applications tbody');
        table.html('');

        var rows = 0;
        $.each(rcmail.env.application_passwords, function(id, props) {
            if (props.active) {
                var tr = $('<tr>').addClass(props.method).appendTo(table),
                    button = $('<a class="button icon delete">').attr({href: '#', rel: id})
                        .append($('<span class="inner">').text(rcmail.get_label('remove','ourfun')));

                $('<td>').addClass('name').text(props.label || props.name).appendTo(tr);
                $('<td>').addClass('created').text(props.created || '??').appendTo(tr);
                $('<td>').addClass('actions buttons-cell').append(button).appendTo(tr);
                rows++;
            }
        });
        table.parent().show();
    }

    $('#ourfun-prop-save-button').on('click', null, function(e) {
        var lock, data, form = $('#ourfun-prop-save'),
            application_name = form.find('input[name="new_application_name"]');

        if (application_name.length && !application_name.val().length) {
            alert(rcmail.get_label('missingapplicationname','ourfun'));
            application_name.select();
            return false;
        }

        data = form_data(form);
        lock = rcmail.set_busy(true, 'saving');
        rcmail.http_post('plugin.ourfun-save', {
            _method: data.id || method,
            _data: JSON.stringify(data),
        }, lock);
    })

    // render list initially
    render();
})
