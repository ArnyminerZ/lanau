<?php

use JetBrains\PhpStorm\Pure;
use Parse\ParseClient;
use Parse\ParseException;
use Parse\ParseObject;
use Parse\ParseQuery;
use Parse\ParseUser;

require 'vendor/autoload.php';

include_once 'secrets.php';

session_start();

$parseConnected = false;

$_PHP_SELF = $_SERVER['PHP_SELF'];

try {
    // Initialize Parse
    ParseClient::initialize($PARSE_APP_ID, null, $PARSE_MASTER_KEY);

    // Set Parse Server
    ParseClient::setServerURL($PARSE_SERVER, $PARSE_MOUNT);

    $health = ParseClient::getServerHealth();
    if ($health['status'] === 200)
        $parseConnected = true;
} catch (Exception $e) {
}

$currentUser = ParseUser::getCurrentUser();
$login_error = null;
$username = $password = "";

/**
 * This makes data input safe from code injection
 * @param $data
 * @return string
 */
#[Pure] function test_input($data): string
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if (isset($_GET["logout"])) {
    echo "Redirecting...";
    ParseUser::logOut();
    header("Location: /");
    die();
}

if ($_SERVER["REQUEST_METHOD"] == "POST")
    try {
        if ($currentUser) {
            $action = test_input($_POST["action"]);
            if ($action == "RESERVE") {
                $date = test_input($_POST["date"]);
                $duration = test_input($_POST["duration"]);
                $people = test_input($_POST["people"]);

                $people_list = explode(',', $people);

                $reservation = new ParseObject($PARSE_RESERVATIONS_CLASS);
                $reservation->set("user", $currentUser);
                $reservation->setArray("others", $people_list);
                $reservation->set("duration", intval($duration));
                $reservation->set("when", new DateTime($date));
                $reservation->save();

                echo "{\"result\":\"ok\"}";
                die(200);
            } else if ($action == "CANCEL") {
                $id = test_input($_POST["id"]);

                $query = new ParseQuery($PARSE_RESERVATIONS_CLASS);
                $reservation = $query->get($id);
                $reservation->destroy();

                echo "{\"result\":\"ok\"}";
                die(200);
            }
        } else {
            $username = test_input($_POST["username"]);
            $password = test_input($_POST["password"]);
            $user = ParseUser::logIn($username, $password);
            // Do stuff after successful login.

            echo "Redirecting...";
            header("Location: /");
            die();
        }
    } catch (ParseException $error) {
        echo $error->getMessage();
        die(500);
    } catch (Exception $e) {
        echo $e;
        die(500);
    }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Libraries -->
    <link rel="stylesheet" href="/vendor/twbs/bootstrap/dist/css/bootstrap.min.css"/>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.13.1/css/all.css'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.5.1/main.min.css"/>

    <!-- Custom styles -->
    <link href="/dist/css/index.css" rel="stylesheet"/>

    <title data-translate="title"></title>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="/" data-translate="title"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTogglerDemo02"
                aria-controls="navbarTogglerDemo02" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarTogglerDemo02">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="/" data-translate="nav-home"></a>
                </li>
            </ul>
            <?php if ($currentUser) { ?>
                <div class="navbar-nav d-flex">
                    <a class="nav-item nav-link" href="/?logout" data-translate="nav-logout"></a>
                </div>
            <?php } ?>
        </div>
    </div>
</nav>

<?php if ($currentUser) { ?>
    <div class="modal fade" id="reserveModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
         aria-labelledby="reserveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-md-down">
            <form class="modal-content" id="reserveForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="reserveModalLabel" data-translate="reservation-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body row">
                    <div class="col-12 col-lg-6">
                        <label for="reserveDate" class="form-label" data-translate="reservation-date"></label>
                        <input type="date" class="form-control" id="reserveDate" readonly/>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label for="reserveTime" class="form-label" data-translate="reservation-time"></label>
                        <input type="time" class="form-control" id="reserveTime" required/>
                    </div>
                    <div class="col-12">
                        <label for="reserveDuration" class="form-label" data-translate="reservation-duration"></label>
                        <input type="time" class="form-control" id="reserveDuration" required/>
                    </div>
                    <div class="col-12">
                        <label for="reservePeopleEnterGroup" class="form-label"
                               data-translate="reservation-people"></label>
                        <div class="input-group" id="reservePeopleEnterGroup">
                            <input type="text" class="form-control" id="reservePeopleEnter"
                                   data-translate-placeholder="reservation-people" aria-describedby="reservePeopleAdd"/>
                            <button class="btn btn-outline-secondary" type="button" id="reservePeopleAdd"
                                    data-translate="reservation-add"></button>
                        </div>
                    </div>
                    <div class="col-12" style="margin-bottom: 2vh">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span id="reserveReservoir"><?php echo $currentUser->get("fullName"); ?></span>
                                <a class="badge bg-secondary rounded-pill" tabindex="0" id="user-delete"
                                   data-bs-toggle="tooltip" data-bs-placement="left"
                                   data-translate-title="reservation-error-self"><i class="fas fa-trash"></i></a>
                            </li>
                        </ul>
                        <ul class="list-group" id="guestUsersList"></ul>
                    </div>
                    <hr/>
                    <div class="col-12 text-muted">
                        <p data-translate="reservation-info"></p>
                        <ol>
                            <li data-translate="reservation-info-1"></li>
                            <li data-translate="reservation-info-2"></li>
                            <li data-translate="reservation-info-3"></li>
                        </ol>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            data-translate="reservation-close"></button>
                    <button type="submit" class="btn btn-primary" id="reservationButton"
                            data-translate="reservation-reserve"></button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="reservationModal" tabindex="-1"
         aria-labelledby="reservationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-md-down">
            <form class="modal-content" id="reserveForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="reservationModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h5>
                        <span data-translate="reservation-content-made-by"></span><span
                                id="reservationModalMadeBy"></span>
                    </h5>
                    <h5 data-translate="reservation-content-guests"></h5>
                    <ul id="reservationModalGuests"></ul>
                    <div class="row">
                        <div class="col-6">
                            <h5 data-translate="reservation-content-start"></h5>
                            <h5 data-translate="reservation-content-end"></h5>
                        </div>
                        <div class="col-6">
                            <h5 id="reservationModalStart"></h5>
                            <h5 id="reservationModalEnd"></h5>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger" id="cancelReservationButton"
                            data-translate="reservation-cancel"></button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            data-translate="reservation-close"></button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal fade" id="reservationCancelModal" tabindex="-1"
         aria-labelledby="reservationCancelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-md-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reservationCancelModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="reservationCancelModalText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-success" data-bs-dismiss="modal"
                            data-translate="reservation-close"></button>
                    <button type="button" class="btn btn-outline-danger" id="reservationCancelConfirmButton"
                            data-translate="reservation-cancel"></button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<div class="container" id="main-container">
    <?php if ($currentUser) { ?>
        <div class="row">
            <div class="col-12 col-lg-3">
                <ul class="nav nav-pills" id="tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="reservation-tab" data-bs-toggle="tab"
                                data-bs-target="#reservation" type="button"
                                role="tab" aria-controls="reservation" aria-selected="true"
                                data-translate="link-reservation">
                        </button>
                    </li>
                </ul>
            </div>
            <div class="col-12 col-lg-9">
                <div class="tab-pane active" id="reservation" role="tabpanel" aria-labelledby="reservation-tab">
                    <div id='calendar'></div>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <h1 data-translate="title-welcome"></h1>
        <div id="carousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#carousel" data-bs-slide-to="0" class="active" aria-current="true"
                        aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#carousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#carousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
            </div>
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="/images/photos/lanau_1.jpg" class="d-block w-100" style="" alt="...">
                </div>
                <div class="carousel-item">
                    <img src="/images/photos/lanau_2.jpg" class="d-block w-100" style="" alt="...">
                </div>
                <div class="carousel-item">
                    <img src="/images/photos/lanau_3.jpg" class="d-block w-100" style="" alt="...">
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#carousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
        <div class="row" id="panels">
            <div class="col-12 col-md-6">
                <div class="card">
                    <form class="card-body" action="<?php echo htmlspecialchars($_PHP_SELF); ?>" method="POST">
                        <h3 class="card-title" data-translate="title-welcome-login"></h3>
                        <div class="mb-3">
                            <label for="loginUsername" class="form-label" data-translate="login-username"></label>
                            <input type="text" class="form-control" name="username" id="loginUsername"/>
                        </div>
                        <div class="mb-3">
                            <label for="loginPassword" class="form-label" data-translate="login-password"></label>
                            <input type="password" class="form-control" name="password" id="loginPassword"/>
                        </div>
                        <button type="submit" class="btn btn-primary float-end" data-translate="login-action"></button>
                    </form>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card">
                    <form class="card-body row">
                        <h3 class="card-title" data-translate="title-welcome-register"></h3>
                        <div class="col-12">
                            <label for="registerEmail" class="form-label" data-translate="register-email"></label>
                            <input type="email" class="form-control" id="registerEmail" readonly/>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="registerPassword" class="form-label" data-translate="register-password"></label>
                            <input type="password" class="form-control" id="registerPassword" readonly/>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="registerPasswordConfirm" class="form-label"
                                   data-translate="register-password-confirm"></label>
                            <input type="password" class="form-control" id="registerPasswordConfirm" readonly/>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="registerName" class="form-label" data-translate="register-name"></label>
                            <input type="text" class="form-control" id="registerName" readonly/>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="registerSurname" class="form-label" data-translate="register-surname"></label>
                            <input type="text" class="form-control" id="registerSurname" readonly/>
                        </div>
                        <div class="col-12" id="final-row">
                            <button class="btn btn-outline-danger" type="button" data-translate="register-complete"
                                    disabled></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<nav class="navbar fixed-bottom navbar-light bg-light" id="footer">
    <div class="container-fluid">
        <p class="text-muted small" data-translate="footer"></p>
        <div class="nav-item dropdown dropup">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button"
               data-bs-toggle="dropdown" aria-expanded="false" data-translate="nav-language"></a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownMenuLink"
                data-languages-list="<li><a class='dropdown-item' href='#' onclick='changeLanguage(%langCodeQ%);return false;'>%langDispName%</a></li>">
            </ul>
        </div>
    </div>
</nav>

<!-- Required Libraries -->
<script src="/vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/ArnyminerZ/JavaScript-Translator@1.4.1/dist/js/translate.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.5.1/main.min.js"></script>

<!-- Constants -->
<script>
    const GUEST_NAME_MIN_LENGTH = <?php echo $GUEST_NAME_MIN_LENGTH; ?>;

    const EVENT_SOURCES = [
        <?php
        if ($currentUser) {
            $query = new ParseQuery($PARSE_RESERVATIONS_CLASS);
            // Get only non-passed events
            $query->greaterThanOrEqualToRelativeTime('when', 'now');
            $results = $query->find();
            for ($i = 0; $i < count($results); $i++) {
                $object = $results[$i];
                $objectId = $object->getObjectId();
                $when = $object->get('when');
                $duration = $object->get('duration');
                $others = $object->get('others');
                $user = $object->get('user');
                $user->fetch();

                $fullName = $user->get('fullName');
                $username = $user->get('username');
                $email = $user->get('email');
                $formatted_others = join(",", $others);
                $formatted_others = str_replace(",", "\"],[\"", $formatted_others);
                if (count($others) > 0)
                    $formatted_others = "\"$formatted_others\"";

                $formatted_when = $when->format("Y-m-d H:i:s");
                $end = $when->modify("+$duration second");
                $formatted_end = $end->format("Y-m-d H:i:s");

                echo "{
id:\"$objectId\",
title:\"Reservation <code>$objectId</code>\",
start:\"$formatted_when\",
end:\"$formatted_end\",
extendedProps: {
  user: {
    username: \"$username\",
    fullName: \"$fullName\",
    email: \"$fullName\"
  },
  guests: [$formatted_others],
  duration: $duration
}
},";
            }
        }
        ?>
    ];
</script>

<!-- Scripts -->
<script src="/dist/js/language.js"></script>
<?php if ($currentUser) { ?>
    <script src="/dist/js/calendar.js"></script>
    <script src="/dist/js/reservator.js"></script>
<?php } ?>
</body>
</html>