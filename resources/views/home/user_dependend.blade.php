@extends('layouts.tri')

@section('body')
    <div class="mt-m">
        <main class="content-wrap card">
	    <h1>{{ user()->name }}</h1>
    <!--<h6>{{ phpinfo() }}</h6>-->
            Hallo Leute,
            diese Seite soll Euch eine bessere Orientierung im Bookstack ermöglichen. Sie wird pro User automatisch generiert. Die generierten Links basieren auf einer Tag basierten Suche.
            Falls Ihr noch weitere Links haben wollt die für alle Mitarbeiter Sinn machen, dann schreibt eine Mail oder sprecht mich (Timo) an. <br>
            <br>
            Euch noch einen tollen Arbeitstag.<br><br>
            <h4>Meine Dokumente</h4>
            <a href="search?term=&filters[created_by]=me">Alle von mir erstellten Dokumente</a><br>
            <a href="search?term=&filters[updated_by]=me">Alle von mir zuletzt angepassten Dokumente</a><br>
            <a href="search?term=&filters[owned_by]=me">Alle mir gehörenden Dokumente</a><br>
            <br>
            <h4>Prozesse</h4>
            @foreach(user()->roles as $role)
                <a href=
                    {{
                        $searchString = "search?term=[role=" . $role["display_name"] . "]" . "[type=process]"
                    }}
                >Relevante Prozesse für meine Rolle: {{$role["display_name"]}}</a><br>
            @endforeach
            <br>
            <h4>Allgemeine Dokumente</h4>
            @foreach(user()->roles as $role)
                <a href=
                        {{
                            $searchString = "search?term=[role=" . $role["display_name"] . "]"
                        }}
                >Alle Dokumente für meine Rolle: {{$role["display_name"]}}</a><br>
            @endforeach
        </main>
    </div>
    <!--@include('shelves.parts.list', ['shelves' => $shelves, 'view' => $view])-->
@stop

@section('left')
    @include('home.parts.sidebar')
@stop

@section('right')
    <!--<div class="actions mb-xl">
        <h5>{{ trans('common.actions') }}</h5>
        <div class="icon-list text-primary">
            @if(user()->can('bookshelf-create-all'))
                <a href="{{ url("/create-shelf") }}" class="icon-list-item">
                    <span>@icon('add')</span>
                    <span>{{ trans('entities.shelves_new_action') }}</span>
                </a>
            @endif
            @include('entities.view-toggle', ['view' => $view, 'type' => 'shelves'])
            @include('home.parts.expand-toggle', ['classes' => 'text-primary', 'target' => '.entity-list.compact .entity-item-snippet', 'key' => 'home-details'])
            @include('common.dark-mode-toggle', ['classes' => 'icon-list-item text-primary'])
        </div>
    </div>-->
@stop
