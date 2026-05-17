<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreTripRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'vehicle_id' => 'required|uuid',
            'driver_id'  => 'nullable|uuid',
            'fleet_id'   => 'required|uuid',
        ];
    }
}
