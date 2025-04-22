$("#accordian").on("click", "li", function (e) {
  e.stopPropagation();

  var submenu = $(this).find(".submenu");

  if (submenu.length > 0) {
    e.preventDefault();
    submenu.slideToggle();
    return;
  }

  var itemId = $(this).attr("id");
  if (!itemId) {
    console.log("Элемент не имеет id, пропускаем");
    return;
  }

  var items = $("#accordian");
  var current = items.find(".active");
  var page = itemId + ".html";

  console.log("Prev: ", current.attr("id"));
  console.log("Current: ", itemId);
  console.log("Page: ", page);

  $("#accordian ul li").removeClass("active");
  $(this).addClass("active");

  localStorage.clear();
  $("#content").load(page);
});
