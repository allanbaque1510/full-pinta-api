<?php

use App\Modules\Billing\BillingServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Directory\DirectoryServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Modules\Reviews\ReviewsServiceProvider;
use App\Modules\Scheduling\SchedulingServiceProvider;
use App\Modules\Staffing\StaffingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    IdentityServiceProvider::class,
    DirectoryServiceProvider::class,
    CatalogServiceProvider::class,
    StaffingServiceProvider::class,
    SchedulingServiceProvider::class,
    ReviewsServiceProvider::class,
    BillingServiceProvider::class,
    NotificationsServiceProvider::class,
];
