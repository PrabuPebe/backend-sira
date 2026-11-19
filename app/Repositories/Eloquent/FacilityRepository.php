<?php

namespace App\Repositories\Eloquent;

use App\Models\Facility;
use App\Repositories\Contracts\FacilityRepositoryInterface;

class FacilityRepository extends BaseRepository implements FacilityRepositoryInterface
{
    public function __construct(Facility $model)
    {
        parent::__construct($model);
    }
}
