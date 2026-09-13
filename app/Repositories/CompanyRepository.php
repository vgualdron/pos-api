<?php

namespace App\Repositories;

use App\Models\Company;

class CompanyRepository
{
    public function all()
    {
        return Company::with('places')->orderBy('created_at', 'asc')->get();
    }

    public function findById($id)
    {
        return Company::with('places')->findOrFail($id);
    }

    public function findByStatuses(string $status)
    {
        $statuses = array_map('trim', explode(',', $status));
        return Company::with('places')->whereIn('status', $statuses)->orderBy('created_at', 'asc')->get();
    }

    public function create(array $data)
    {
        return Company::create($data);
    }

    public function update($id, array $data)
    {
        $model = Company::findOrFail($id);
        return $model->update($data);
    }

    public function delete($id)
    {
        $model = Company::findOrFail($id);
        return $model->delete();
    }
}
