const updateDeletables = () => {
    const deletables = document.getElementsByClassName('deletable')
    for (const d in deletables)
        if (deletables.hasOwnProperty(d)) {
            const deletable = $(deletables[d])
            deletable.on('click', function (event) {
                event.preventDefault()
                deletable.parent().remove()
            })
        }
}

$(() => {
    const template =
        `<li class="list-group-item d-flex justify-content-between align-items-center" data-name="{name}">
            <span id="reserveReservoir">{name}</span>
            <a class="badge bg-danger rounded-pill deletable" href="#"><i class="fas fa-trash"></i></a>
         </li>`;

    const exampleEl = document.getElementById('user-delete');
    new bootstrap.Tooltip(exampleEl, {});

    const reserveForm = $("#reserveForm")
    const personNameField = $("#reservePeopleEnter")
    const guestUsersList = $("#guestUsersList")
    const guestUsersAdd = $("#reservePeopleAdd")
    const reserveDateField = $("#reserveDate")
    const reserveTimeField = $("#reserveTime")
    const reserveDurationField = $("#reserveDuration")
    guestUsersAdd.on('click', function () {
        const name = personNameField.val()
        if (name.length > GUEST_NAME_MIN_LENGTH) {
            guestUsersList.append(
                template.replace(/{name}/g, name)
            )
            personNameField.val(null)
            updateDeletables()
        }
    })
    reserveForm.on('submit', function (event) {
        event.preventDefault()

        const date = reserveDateField.val()
        const time = reserveTimeField.val()
        const duration = reserveDurationField.val()
        const guestUsersRaw = guestUsersList.children()
        const guestUsersArray = []

        reserveTimeField.prop('disabled', true)
        reserveDurationField.prop('disabled', true)
        personNameField.prop('disabled', true)

        for (const u in guestUsersRaw)
            if (guestUsersRaw.hasOwnProperty(u)) {
                const userRaw = guestUsersRaw[u]
                if (userRaw.innerText)
                    guestUsersArray.push(userRaw.innerText.replace(/,/g, null))
            }
        const guestUsers = guestUsersArray.join()
        const dateTime = new Date(`${date} ${time}`)

        $.post('/', {
            duration: duration.getHours() * 3600 + duration.getMinutes() * 60,
            date: dateTime.toISOString(),
            people: guestUsers
        }).done(function (data) {
            console.log(data);

            reservationModal.hide();

            reserveTimeField.prop('disabled', false);
            reserveDurationField.prop('disabled', false);
            personNameField.prop('disabled', false);

            reserveForm.reset();
            guestUsersList.empty();
        }).fail(function (error) {
            alert("Could not reserve")
            console.error(error)
        })
    })
})