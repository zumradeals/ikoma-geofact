<?php
namespace App\Http\Requests;
use App\Core\Canonical\CanonicalEventFactory;
use Illuminate\Foundation\Http\FormRequest;
class ConnectorIngestRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        $validTypes = implode(',', CanonicalEventFactory::validEventTypes());
        return [
            'event_type' => 'nullable|string|in:' . $validTypes,
            'device_id'  => 'nullable|string',
            'timestamp'  => 'nullable|string',
        ];
    }
}
