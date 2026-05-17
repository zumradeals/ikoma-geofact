<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreVehicleRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'name'     => 'required|string|max:100',
            'plate'    => 'required|string|max:20',
            'fleet_id' => 'required|uuid',
            'brand'    => 'nullable|string|max:60',
            'model'    => 'nullable|string|max:60',
            'year'     => 'nullable|integer|min:1990|max:2030',
        ];
    }
}
