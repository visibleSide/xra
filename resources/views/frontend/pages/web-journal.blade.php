@php
    $app_local    = get_default_language_code();
@endphp
@extends('frontend.layouts.master')

@section('content')
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    Start Blog
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
<section class="blog pt-150 pb-80">
    <div class="container mx-auto">
        <div class="text-content">
            <h4 class="title">{{ $journal_section->value->language->$app_local->title ?? "" }}</h4>
            <h3 class="title">{{ $journal_section->value->language->$app_local->heading ?? "" }}</h3>
        </div>
        <div class="row g-5 ptb-40">
            @foreach ($journals as $item)
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="card">
                        <div class="card-thumb">
                            <img src="{{ get_image($item->data->image ?? null , 'site-section') }}" class="img-fluid main-img" alt="image">
                        </div>
                        <div class="card-body">
                            <a href="{{ setRoute('frontend.journal.details',$item->slug) }}">
                                <h5 class="text-capitalize fw-bold fs-4 title">{{ Str::words($item->data->language->$app_local->title ?? '','5','...') ?? "" }}
                                </h5>

                                <p class="card-text"> {!! Str::words($item->data->language->$app_local->description ?? '','10','...') !!}</p>
                            </a>
                            <a href="{{ setRoute('frontend.journal.details',$item->slug)}}" class="link">Read More <i class="las la-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    End Blog
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
@endsection