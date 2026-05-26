$( document ).ready(function() {
   $('.menu-i').removeClass('active');
   $('.sub-menu').slideUp();

   $('.menu-i').click(function(){
      let menu = $(this);
      let submenu = menu.find('.sub-menu');

      if (menu.hasClass('active')) {
         menu.removeClass('active');
         submenu.slideUp();
      }else {
         $('.menu-i').removeClass('active');
         $('.sub-menu').slideUp();
         menu.addClass('active');
         submenu.slideDown();
      }
   })
});