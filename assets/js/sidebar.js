$("#accordian").on("click","li",function(e) {
  var items = $('#accordian');
  var current = items.find('.active');
  var page =  $(this).attr('id') + '.html';
  console.log("Prev: ", current.attr('id'));
  console.log("Current: ", $(this).attr('id'))     
  console.log("Page: ",page);
  $('#accordian ul li').removeClass("active");
  $(this).addClass('active');
  localStorage.clear();
  $('#content').load(page);
});