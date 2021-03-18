let reservationModal, reservationViewModal, reservationCancelModal;
let currentEvent;

$(() => {
    const reserveDateField = $('#reserveDate')

    reservationModal = new bootstrap.Modal(document.getElementById('reserveModal'), {
        keyboard: false,
        backdrop: 'static'
    });
    const reservationViewModalDOM = document.getElementById('reservationModal');
    reservationViewModal = new bootstrap.Modal(reservationViewModalDOM, {});
    const reservationCancelModalDOM = document.getElementById('reservationCancelModal')
    reservationCancelModal = new bootstrap.Modal(reservationCancelModalDOM, {});

    reservationCancelModalDOM.addEventListener('show.bs.modal', function () {
        reservationViewModal.hide();
    });
    reservationCancelModalDOM.addEventListener('hide.bs.modal', function () {
        reservationViewModal.show();
    });

    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        themeSystem: 'bootstrap',
        events: EVENT_SOURCES
    });
    calendar.on('dateClick', function(info) {
        const date = info.date
        const day = ("0" + date.getDate()).slice(-2);
        const month = ("0" + (date.getMonth() + 1)).slice(-2);
        const thatDay = date.getFullYear() + "-" + (month) + "-" + (day);
        reserveDateField.val(thatDay)
        reservationModal.show()
    });
    calendar.on('eventClick', function(info) {
        const event = info.event;
        const id = event.id,
            start = event.start,
            end = event.end;
        const props = event.extendedProps;
        const user = props.user;
        const madeBy = user.fullName;
        const guests = props.guests;

        currentEvent = event;

        const reservationModalLabel = $('#reservationModalLabel');
        const madeByLabel = $('#reservationModalMadeBy');
        const guestsField = $("#reservationModalGuests");
        const startField = $("#reservationModalStart");
        const endField = $("#reservationModalEnd");

        reservationModalLabel.html(getTranslation('reservation-content-title') + `<code>${id}</code>`);
        madeByLabel.text(madeBy);
        guestsField.empty();
        for (const g in guests)
            if (guests.hasOwnProperty(g)) {
                const guest = guests[g]
                guestsField.append(`<li><h5>${guest}</h5></li>`)
            }
        startField.text(start.toLocaleString());
        endField.text(end.toLocaleString());

        reservationViewModal.show()
    });
    calendar.render();
})