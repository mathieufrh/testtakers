<?php

namespace App\Policies;

use App\User;
use App\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as Authenticatable;

class ModelPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine whether the model has been created by the given user.
     *
     * @param \App\Model|Authenticatable $model
     * @param \App\User $user
     * @return bool
     */
    protected function wasCreatedBy($model, User $user)
    {
        if (Schema::hasColumn($model->getTable(), 'user_id')) {
            return $user->id === $model->user_id;
        } elseif (Schema::hasColumn($model->getTable(), 'created_by')) {
            return $user->id === $model->user_id;
        }

        return false;
    }

    /**
     * Grant all abilities to administrator.
     *
     * @param  \App\User  $user
     * @return bool
     */
    public function before(User $user)
    {
        if ($user->isAdmin) {
            return true;
        }
    }

    /**
     * Determine whether the user can store the model.
     *
     * @param \App\User $user
     * @param \App\Model|Authenticatable $model
     * @return bool
     */
    public function store(User $user, $model)
    {
        return $this->wasCreatedBy($model, $user);
    }

    /**
     * Determine whether the user can fetch the model.
     *
     * @param \App\User $user
     * @param \App\Model|Authenticatable $model
     * @return bool
     */
    public function fetch(User $user, $model)
    {
        return $this->wasCreatedBy($model, $user);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param \App\User $user
     * @param \App\Model|Authenticatable $model
     * @return bool
     */
    public function delete(User $user, $model)
    {
        return $this->wasCreatedBy($model, $user);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param \App\User $user
     * @param \App\Model|Authenticatable $model
     * @return bool
     */
    public function update(User $user, $model)
    {
        return $this->wasCreatedBy($model, $user);
    }
}
