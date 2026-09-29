@extends('layout')

@section('content')
    <h1>{{ isset($spot) ? 'スポット編集' : 'スポット登録' }}</h1>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ isset($spot) ? route('spot.update', $spot) : route('spot.store') }}" method="POST">
        @csrf
        @if (isset($spot)) @method('PUT') @endif

        <div class="mb-3">
            <label for="name" class="form-label">スポット名</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $spot->name ?? '') }}" required maxlength="255">
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="latitude" class="form-label">緯度</label>
                <input type="number" step="any" min="-90" max="90" class="form-control" id="latitude" name="latitude" value="{{ old('latitude', $spot->latitude ?? '') }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label for="longitude" class="form-label">経度</label>
                <input type="number" step="any" min="-180" max="180" class="form-control" id="longitude" name="longitude" value="{{ old('longitude', $spot->longitude ?? '') }}" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">保存</button>
        <a href="{{ route('spot.index') }}" class="btn btn-secondary">キャンセル</a>
    </form>
@endsection
