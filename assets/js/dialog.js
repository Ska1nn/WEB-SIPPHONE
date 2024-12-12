$(document).ready(function () {
    $.get("php/language.php", function (data) {
        const response = JSON.parse(data);
        const currentLanguage = response.language;
        const currentPage = getPageFromConsole();
        
        loadTranslations(currentLanguage, currentPage, function(translations) {
            window.translations = translations;
        });
    });
});

function loadTranslations(language, pageName, callback) {
    const translationFilePath = `/languages/${pageName}.json`;
    const indexFilePath = '/languages/index.json';

    $.when(
        $.getJSON(translationFilePath),
        $.getJSON(indexFilePath)
    ).done(function (pageTranslations, indexTranslations) {
        const combinedTranslations = Object.assign(
            {},
            indexTranslations[0][language] || {},
            pageTranslations[0][language] || {}
        );
        applyTranslations(combinedTranslations);
        
        if (callback) callback(combinedTranslations);
    });
}
function showDialog(result, restart) {
    if (result == "1") {
        $('#modal-dialog-text').html(`<p id="configuration_saved">${window.translations['configuration_saved'] || 'Конфигурация сохранена!'}</p>`);
        if (restart === true) {
            $('#modal-dialog-descritpion').html(`<p id="text-info-1">${window.translations['text-info-1'] || 'Для того чтобы изменения вступили в силу, необходимо перезапустить приложение.<br/>Перезагрузить приложение можно в меню<br/>"Системные настройки".'}</p>`);
        } else {
            $('#modal-dialog-descritpion').html("");
        }

        $('.modal-content').css({'background': 'white'});
        $('#ok-button').css({'background': '#7BAF21'});
    } else {
        $('#modal-dialog-text').html(`<p id="there-are-problems-1">${window.translations['there-are-problems-1'] || 'Возникли проблемы!'}</p>`);
        $('#modal-dialog-descritpion').html(`<p id="configuration_no-saved">${window.translations['configuration_no-saved'] || 'Конфигурация не была сохранена.'}</p>`);
        $('.modal-content').css({'background': '#FFC6CC'});
        $('#ok-button').css({'background': 'red'});
    }

    $('#confirm').modal();

    $('#ok-button').off('click').on('click', function() {
        if (result == "1") {
            $('#reloadButton').fadeIn();
        } else {
            $('#confirm').modal('hide');
        }
    });
}

function showDialogRestart(result, restart) {
    if (result == "1") {
        $('#modal-dialog-descritpion').html(`<p id="text-info-1">${window.translations['text-info-1'] || 'Для того чтобы изменения вступили в силу, необходимо перезапустить приложение.<br/>Перезагрузить приложение можно в меню<br/>"Системные настройки".'}</p>`);
        
        // Если перезагрузка необходима, показываем информацию
        if (restart === true) {
            $('#modal-dialog-descritpion').html(`<p id="configuration_no-saved">${window.translations['configuration_no-saved'] || 'Приложение перезапущено!'}</p>`);
        } else {
            $('#modal-dialog-descritpion').html("");
        }

        $('.modal-content').css({'background': 'white'});
        $('#ok-button').css({'background': '#7BAF21'});
    } else {
        $('#modal-dialog-text').html(`<p id="there-are-problems-1">${window.translations['there-are-problems-1'] || 'Возникли проблемы!'}</p>`);
        $('#modal-dialog-descritpion').html(`<p id="configuration_no-saved">${window.translations['configuration_no-saved'] || 'Конфигурация не была сохранена.'}</p>`);
        $('.modal-content').css({'background': '#FFC6CC'});
        $('#ok-button').css({'background': 'red'});
    }

    $('#confirm').modal();

    $('#ok-button').off('click').on('click', function() {
        if (result == "1") {
            $('#reloadButton').fadeIn();
        } else {
            $('#confirm').modal('hide');
        }
    });
}

$(document).ready(function () {
    $('#delete-contacts-button').on('click', function() {
        $('#deleteConfirm').modal('show');
    });

    $('#delete-no-click').on('click', function() {
        $('#deleteConfirm').modal('hide');
    });

    $('#delete-contacts').on('click', function() {
        $('#deleteConfirm').modal('hide');
        
        var json = JSON.stringify({ command: 'delete' });

        $.post('php/contacts.php', json, function(resp) {
            console.log(resp);

            var jsonResponse = JSON.parse(resp);

            if (jsonResponse.command === "delete") {
                console.log(jsonResponse.message);
            } else if (jsonResponse.success) {
                showDeleteDialog(jsonResponse.success);
            } else {
                console.error('Ошибка при удалении контактов');
            }
        });
    });
});
function showDeleteDialog(result) {
    if (result == "1") {
        $('#modal-dialog-text').html(`<p id="contacts-deleted">${window.translations['contacts-deleted'] || 'Контакты успешно удалены!'}</p>`);
        $('#modal-dialog-description').html("");
        $('.modal-content').css({'background': 'white'});
        $('#ok-button').css({'background': '#7BAF21'});
    } else {
        $('#modal-dialog-text').html(`<p id="there-are-problems">${window.translations['there-are-problems'] || 'Возникли проблемы!'}</p>`);
        $('#modal-dialog-description').html(`<p id="contacts-not-deleted">${window.translations['contacts-not-deleted'] || 'Контакты не были удалены.'}</p>`);
        $('.modal-content').css({'background': '#FFC6CC'});
        $('#ok-button').css({'background': 'red'});
    }

    $('#confirm').modal();
}

$(document).ready(function () {
    $('#reloadButton').on('click', function () {
        $('#reloadConfirm').modal('show');
        $('#reboot-button-click').css('display', 'inline-block');
        $('#restart-button-click').css('display', 'none');
    });

    $('#restartButton').on('click', function () {
        $('#reloadConfirm').modal('show');
        $('#reboot-button-click').css('display', 'none');
        $('#restart-button-click').css('display', 'inline-block');
    });

    $('#re-no-click').on('click', function () {
        $('#reloadConfirm').modal('hide');
    });
    
    $('#reboot-button-click').on("click", function() { 
        $('#reloadConfirm').modal('hide');
        var json = JSON.stringify({ command: 'reboot' });
        
        $.post('php/system.php', json, function(resp) {
            console.log(resp);
            var json = JSON.parse(resp);
            showRebootDialog(json.success);
        });
    });

    $('#restart-button-click').on("click", function() { 
        $('#reloadConfirm').modal('hide');
        var json = JSON.stringify({ command: 'reset' });
        
        $.post('php/system.php', json, function(resp) {
            console.log(resp);
            var json = JSON.parse(resp);
            showRebootDialog(json.success);
        });
    });
});
/*
// Диалоговое окно для подтверждения перезагрузки
$('#confirm-reboot-dialog').on('click', '#confirm-reboot', function() {
    // Закрыть окно подтверждения
    $('#confirm-reboot-dialog').modal('hide');

    // Выполнить запрос на перезагрузку
    var json = JSON.stringify({ command: 'reboot' });
    $.post('php/system.php', json, function(resp) {
        console.log(resp);
        var json = JSON.parse(resp);
        showRebootDialog(json.success); // Показать результат перезагрузки
    });
});

// Обработчик для кнопки "Нет" в диалоговом окне
$('#confirm-reboot-dialog').on('click', '.btn-secondary', function() {
    // Закрыть диалог без перезагрузки
    $('#confirm-reboot-dialog').modal('hide');
});
*/
// Функция отображения результата перезагрузки
function showRebootDialog(result) {
    if (result == "1") {
        $('#modal-dialog-text').html(`<p id="phone-restarted">${window.translations['phone-restarted'] || 'Телефон перезагружен!'}</p>`);
        $('#modal-dialog-descritpion').html("");
        $('.modal-content').css({'background': 'white'});
        $('#ok-button').css({'background': '#7BAF21'});
    } else {
        $('#modal-dialog-text').html(`<p id="there-are-problems-3">${window.translations['there-are-problems-3'] || 'Возникли проблемы!'}</p>`);
        $('#modal-dialog-descritpion').html(`<p id="phone-no-restarted">${window.translations['phone-no-restarted'] || 'Телефон не был перезагружен.'}</p>`);
        $('.modal-content').css({'background': '#FFC6CC'});
        $('#ok-button').css({'background': 'red'});
    }

    // Показать результат перезагрузки
    $('#confirm').modal();
};

function showRestartDialog(result){
    if ( result == "1" ) {
        $('#modal-dialog-text').html(`<p id="application-restarted">${window.translations['application-restarted'] || 'Приложение перезапущено!'}</p>`);
        $('#modal-dialog-descritpion').html("");
        $('.modal-content').css({'background' : 'white'});
        $('#ok-button').css({'background' : '#7BAF21'});
    }
    else {
        $('#modal-dialog-text').html(`<p id="there-are-problems-2">${window.translations['there-are-problems-2'] || 'Возникли проблемы!'}</p>`);
        $('#modal-dialog-descritpion').html(`<p id="application_no-restarted">${window.translations['application_no-restarted'] || 'Приложение не было перезапущено.'}</p>`);
        $('.modal-content').css({'background' : '#FFC6CC'});
        $('#ok-button').css({'background' : 'red'});
    }  
    $('#confirm').modal();
};

function showRebootDialog(result){
    if ( result == "1" ) {
        $('#modal-dialog-text').html(`<p id="phone-restarted">${window.translations['phone-restarted'] || 'Телефон перезагружен!'}</p>`);
        $('#modal-dialog-descritpion').html("");
        $('.modal-content').css({'background' : 'white'});
        $('#ok-button').css({'background' : '#7BAF21'});
    }
    else {
        $('#modal-dialog-text').html(`<p id="there-are-problems-3">${window.translations['there-are-problems-3'] || 'Возникли проблемы!'}</p>`);
        $('#modal-dialog-descritpion').html(`<p id="phone-no-restarted">${window.translations['phone-no-restarted'] || 'Телефон не был перезагружен.'}</p>`);
        $('.modal-content').css({'background' : '#FFC6CC'});
        $('#ok-button').css({'background' : 'red'});
    }  
    $('#confirm').modal();
};

function showResetDialog(result){
    if ( result == "1" ) {
        $('#modal-dialog-text').html(`<p id="factory_reset_done">${window.translations['factory_reset_done'] || 'Сброс до заводских настроек произведен!'}</p>`);
        $('#modal-dialog-descritpion').html("");
        $('.modal-content').css({'background' : 'white'});
        $('#ok-button').css({'background' : '#7BAF21'});
    }
    else {
        $('#modal-dialog-text').html(`<p id="there-are-problems-4">${window.translations['there-are-problems-4'] || 'Возникли проблемы!'}</p>`);
        $('#modal-dialog-descritpion').html(`<p id="factory_reset_no">${window.translations['factory_reset_no'] || 'Сброс до заводских настроек не произведен.'}</p>`);
        $('.modal-content').css({'background' : '#FFC6CC'});
        $('#ok-button').css({'background' : 'red'});
    }  
    $('#confirm').modal();
};

function showNotCompleteDialog(){
    $('#modal-dialog-text').html(`<p id="error_config">${window.translations['error_config'] || 'Ошибка конфигурации!'}</p>`);
    $('#modal-dialog-descritpion').html(`<p id="please-required">${window.translations['please-required'] || 'Пожалуйста введите все необходимые данные.'}</p>`);
    $('.modal-content').css({'background' : '#FFC6CC'});
    $('#ok-button').css({'background' : 'red'});
    $('#confirm').modal();
};


