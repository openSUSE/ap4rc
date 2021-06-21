window.rcmail && rcmail.addEventListener('init', function(evt) {
    if (!rcmail.env.application_passwords) {
        rcmail.env.application_passwords = {};
    }
    function render() {
        var table = $('#ourfun tbody');
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

        table.parent()[(rows > 0 ? 'show' : 'hide')]();
    }
    // render list initially
    render();
}
