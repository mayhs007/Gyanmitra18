<div class="row">
    <div class="col s6 input-field">
        <i class="material-icons prefix">account_circle</i>
        {!! Form::label('first_name') !!}
        {!! Form::text('first_name') !!}
    </div>

    <div class="col s6 input-field">
        <i class="material-icons prefix">account_circle</i>
        {!! Form::label('last_name') !!}
        {!! Form::text('last_name') !!}
    </div>
</div>
<div class="row">
    <div class="col s12 input-field">
        <i class="material-icons prefix">email</i>                    
        {!! Form::label('email') !!}
        {!! Form::text('email') !!}
    </div>
</div>
<div class="row">
    <div class="col s12">
        <i class="fa fa-2x fa-transgender prefix"></i> 
        {!! Form::radio('gender', 'male', null, ['id' => 'male', 'checked' => 'true']) !!}
        {!! Form::label('male') !!}                     
        {!! Form::radio('gender', 'female', null, ['id' => 'female']) !!}       
        {!! Form::label('female') !!}
    </div>
</div>
<div class="row">
    <?php
        $college_list = [];
        foreach(App\College::all() as $college){
            $college_list[$college->id] = $college->getQualifiedName();
        }
        ?>
    <div class="col s12 input-field">
        <i class="fa fa-2x fa-graduation-cap prefix"></i>                     
        {!! Form::select('college_id', $college_list) !!}
        <p class="red-text"><i class="fa fa-question-circle"></i> Is your college not listed? contact us 9677913395</p>
    </div>
</div>
<div class="row">
    <div class="col s12">
        <i class="fa fa-2x fa-transgender prefix"></i> 
        {!! Form::radio('level_of_study', 'UG', null, ['id' => 'UG', 'checked' => 'true']) !!}
        {!! Form::label('UG') !!}                     
        {!! Form::radio('level_of_study', 'PG', null, ['id' => 'PG']) !!}       
        {!! Form::label('PG') !!}
    </div>
</div>
<div class="row">
    <div class="col s12 input-field">
        <i class="material-icons prefix">call</i>
        {!! Form::label('mobile') !!}
        {!! Form::text('mobile') !!}
    </div>
</div>
<div class="row">
    <div class="col s12 input-field">
        {!! Form::submit('Submit', ['class' => 'btn waves-effect waves-light green']) !!}
    </div>
</div>