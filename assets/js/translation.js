$(document).ready(function () {
    $.get("php/language.php", function (data) {
        const response = JSON.parse(data);
        const currentLanguage = response.language;

        const currentPage = getPageFromConsole();
        loadTranslations(currentLanguage, currentPage);
    });
});

function getPageFromConsole() {
    const currentPageElement = $('#accordian .active');
    const currentPage = currentPageElement.attr('id');
    console.log(`Page: ${currentPage}`);
    return currentPage;
}

function loadTranslations(language, pageName) {
    const translationFilePath = `/languages/${pageName}.json`;
    const indexFilePath = '/languages/index.json';

    $.when(
        $.getJSON(translationFilePath),
        $.getJSON(indexFilePath)
    ).done(function (pageTranslations, indexTranslations) {

        if (pageTranslations[0][language]) {
            applyTranslations(pageTranslations[0][language]); 
        } else {
            console.error(`Translation not available for language: ${language} in ${pageName}`);
        }

        if (indexTranslations[0][language]) {
            applyTranslations(indexTranslations[0][language]);
        } else {
            console.error(`Translation not available for language: ${language} in index.json`);
        }

    }).fail(function () {
        console.error(`One or both translation files not found: ${translationFilePath} or ${indexFilePath}`);
    });
}

function applyTranslations(languageTranslations) {
    for (const key in languageTranslations) {
        if (languageTranslations.hasOwnProperty(key)) {
            const element = $(`#${key}`);

            if (element.length === 0) continue;

            if (element.is('input')) {
                element.attr('placeholder', languageTranslations[key]);
            } else if (element.is('option')) {
                element.text(languageTranslations[key]);
            } else if (element.children('p').length > 0) {
                element.children('p').text(languageTranslations[key]);
            } else {
                element.text(languageTranslations[key]);
            }
        }
    }
}
