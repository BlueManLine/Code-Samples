var Games = {

    init: function() {
        //alert('init action');
    },
    
    createAction: function()
    {
        this.datePickerSetup();
    },
    
    gameSetupAction: function()
    {
        if( game_complete==0 ) {
        
          this.datePickerSetup();
          
          $('.btn-add').click(function(){
              ++question_id;
              var question_template = Games.getQuestionCode(question_id);
              $('div.questions').append(question_template);
              
              $('.form-group').show();
              $('.template-answer .form-group').hide();
              $('.question:hidden').effect('slide',{direction:'up'})
              $('.template-question .question').hide();
              
              $('input[name="question['+question_id+']"]').focus();
          });
          
          $(document).on('click', '.btn-add-answer', function(event){
              var $questionBlock = $(this).closest('.question');
              var questionid = $questionBlock.attr('data-questionid');
              
              var answer_code = Games.getAnswerCode(questionid);
              $questionBlock.find('div.answers').append(answer_code);
              
              $('.form-group:hidden').effect('slide',{direction:'up'})
              $('.template-answer .form-group').hide();
              
              $('input[name="new_qu_answer['+questionid+'][]"]').focus();
          });
        
        } else {
            // game ended - disabling all form elements
            $('#question_setup :input').attr('disabled', true);
        }
    },
    
    datePickerSetup: function()
    {
        var curDate = new Date();

        $('.gamestart:not([readonly])').datetimepicker({
            'dateFormat' : 'yy-mm-dd',
            stepMinute: 5,
            'minDate' : new Date(curDate.getFullYear(),curDate.getMonth(),curDate.getDate(),0,0,0,0),
            'showButtonPanel' : false,
            'maxDate': $('.gameend').datetimepicker('getDate'),
            onSelect: function(selectedDate, datepickerInstance) {
                //$('.gamestart').datepicker('hide');
            },
            onClose: function( selectedDate ) {
                $('.gameend').datepicker( "option", "minDate", selectedDate );
            }
        });
        
        $('.gameend:not([readonly])').datetimepicker({
            'dateFormat' : 'yy-mm-dd',
            stepMinute: 5,
            'minDate' : new Date(curDate.getFullYear(),curDate.getMonth(),curDate.getDate(),0,0,0,0),
            onSelect: function(selectedDate, datepickerInstance) {
                //$('.gameend').datepicker('hide');
            },
            onClose: function( selectedDate ) {
                //$('.gamestart').datepicker( "option", "maxDate", selectedDate );
            }
        });
    },
    
    getAnswerCode: function(question_id, answer_id)
    {
        var answer_template = $('div.template-answer').html().replace(/{question_id}/gi,question_id);
        answer_id = typeof(answer_id)=="undefined" ? '' : answer_id ;
        answer_template = answer_template.replace(/{answer_id}/gi, answer_id);
        return answer_template;
    },
    
    getQuestionCode: function(question_id, answer_id)
    {
        var question_template = $('div.template-question').html().replace(/{question_id}/gi,question_id);
        var $question_template = $(question_template);
        
        if( typeof(answer_id)=="undefined" ) {
            // adding two default answers fields
            var answer_code = Games.getAnswerCode(question_id);
            $question_template.find('.answers').append(answer_code);
            $question_template.find('.answers').append(answer_code);
        } else if( typeof(answer_id)=="object" ) {
            
        }
        
        return $question_template;
    }

};
