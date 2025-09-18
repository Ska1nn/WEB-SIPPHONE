$("#accordian").on("click", "li", function (e) {
    e.stopPropagation();

    var submenu = $(this).children(".submenu"); // прямое подменю

    if (submenu.length > 0) {
        e.preventDefault();

        if (submenu.is(":visible")) {
            submenu.slideUp();
            $(this).removeClass("open");
        } else {
            // закрываем все открытые подменю кроме текущего родителя
            $("#accordian .submenu").not(submenu).slideUp();
            $("#accordian li.open").not($(this)).removeClass("open");

            submenu.slideDown();
            $(this).addClass("open");
        }
        return;
    }

    // Клик на пункт без подменю
    // закрываем все подменю кроме тех, которые являются родителями текущего пункта
    $("#accordian .submenu").each(function () {
        if (!$(this).has($(this).find("#" + $(e.target).closest("li").attr("id"))).length) {
            $(this).slideUp();
            $(this).parent("li").removeClass("open");
        }
    });

    // загрузка страницы
    var itemId = $(this).attr("id");
    if (!itemId) {
        console.log("Элемент не имеет id, пропускаем");
        return;
    }

    var page = itemId + ".html";

    console.log("Prev: ", $("#accordian li.active").attr("id"));
    console.log("Current: ", itemId);
    console.log("Page: ", page);

    $("#accordian ul li").removeClass("active");
    $(this).addClass("active");

    localStorage.clear();
    $("#content").load(page);
});
