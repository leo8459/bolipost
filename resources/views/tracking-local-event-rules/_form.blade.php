@csrf
<div class="card-body">
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label for="source_table">Fuente</label>
                <select name="source_table" id="source_table" class="form-control" required>
                    @foreach ($sourceOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('source_table', $rule->source_table) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label for="event_id">Event ID</label>
                <input type="number" min="1" name="event_id" id="event_id" class="form-control" value="{{ old('event_id', $rule->event_id) }}">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label for="is_visible">Mostrar en tracking</label>
                <select name="is_visible" id="is_visible" class="form-control" required>
                    <option value="1" @selected((string) old('is_visible', (int) $rule->is_visible) === '1')>Si</option>
                    <option value="0" @selected((string) old('is_visible', (int) $rule->is_visible) === '0')>No</option>
                </select>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="raw_name">Nombre original</label>
        <input type="text" name="raw_name" id="raw_name" class="form-control" maxlength="255" value="{{ old('raw_name', $rule->raw_name) }}">
    </div>

    <div class="form-group">
        <label for="display_name">Nombre visible</label>
        <input type="text" name="display_name" id="display_name" class="form-control" maxlength="255" value="{{ old('display_name', $rule->display_name) }}">
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                <label for="sort_order">Orden</label>
                <input type="number" min="0" name="sort_order" id="sort_order" class="form-control" value="{{ old('sort_order', $rule->sort_order ?? 0) }}">
            </div>
        </div>
        <div class="col-md-9">
            <div class="form-group">
                <label for="notes">Notas</label>
                <textarea name="notes" id="notes" rows="3" class="form-control">{{ old('notes', $rule->notes) }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card-footer d-flex justify-content-between">
    <a href="{{ route('tracking-local-event-rules.index') }}" class="btn btn-default">Volver</a>
    <button type="submit" class="btn btn-primary">Guardar</button>
</div>
