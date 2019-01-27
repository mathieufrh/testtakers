<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TestTaker extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'login'     => $this->login,
            'password'  => $this->password,
            'title'     => $this->title,
            'lastname'  => $this->lastname,
            'firstname' => $this->firstname,
            'gender'    => $this->gender,
            'email'     => $this->email,
            'picture'   => $this->picture,
            'address'   => $this->address
        ];
    }
}
