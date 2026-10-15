<?php

// Device categories, in navigation order. Each one is a module under
// app/Modules/<Name> with a Category class (see app/Devices/Category.php).
// To add a category (e.g. printers): create the module, add its detail table in
// a new database/migrations file, and add its class here.

return [
    App\Modules\StudyTouch\StudyTouchCategory::class,
    App\Modules\Cctv\CctvCategory::class,
    App\Modules\Chromebooks\ChromebookCategory::class,
];
