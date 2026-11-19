<?php

namespace App\Services;

use App\Repositories\Contracts\FacilityRepositoryInterface;
use App\Services\Contracts\FacilityServiceInterface;

class FacilityService extends BaseService implements FacilityServiceInterface
{
    protected FacilityRepositoryInterface $facilityRepository;

    public function __construct(FacilityRepositoryInterface $facilityRepository)
    {
        parent::__construct($facilityRepository);
        $this->facilityRepository = $facilityRepository;
    }
}
