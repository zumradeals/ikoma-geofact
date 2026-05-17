<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class VehicleTransferRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'target_fleet_id'         => 'required|uuid',
            'effective_date'          => 'required|date',
            'historical_data_policy'  => 'required|in:keep,transfer,split',
            'transfer_note'           => 'nullable|string|max:500',
        ];
    }
}
