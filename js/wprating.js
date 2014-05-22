jQuery(document).ready(function($){
    
    function updateStars(reference,data){
        $(reference).parent().parent().find('div.current-rating span.rating').text(data.rating);
        $(reference).parent().parent().find('div.current-rating span.votes').text(data.votes);
        rating = Math.round(data.rating);
        count = 1;
        $(reference).parent().unbind('mouseenter');
        $(reference).parent().unbind('mouseleave');
        $(reference).parent().children().unbind('mouseenter');
        $(reference).parent().children().unbind('click');
        $(reference).parent().children().each(function(){
            if (count <= rating){
                $(this).addClass('star-on');
            }
            else{
                $(this).removeClass('star-on');
            }
            count++;
        });
        $(reference).parent().children().removeClass('star-temp');
        $(reference).parent().children().removeClass('star-temp-on');        
    }
    
    $(".rating-stars").on('mouseenter',function(){
        $(this).find('a').addClass('star-temp');
    });
    $(".rating-stars").on('mouseleave',function(){
        $(this).find('a').removeClass('star-temp');
        $(this).find('a').removeClass('star-temp-on');
    });
    $(".rating-stars a").on('mouseenter',function(){
        $(this).nextAll().removeClass('star-temp-on');
        $(this).addClass('star-temp-on');
        $(this).prevAll().addClass('star-temp-on');
    });
    
    $(".rating-stars a").on('click',function(){
        postdata = $(this).attr('value');
        referenceStar = this;
        postdata = postdata.split('_');
        data = {
            action : 'add_rating_vote',
            rating_vote : postdata[1],
            security : WPRating.security,
            post_id : postdata[0]
        }
        $.post(WPRating.ajaxurl,data,function(response){
            if (typeof response.error === 'undefined'){
                updateStars(referenceStar,response);
            }
        }, 'json');
    });
});