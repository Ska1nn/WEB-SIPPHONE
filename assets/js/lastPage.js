class LastPage {
    constructor() {
        this.defaultPageId = "status";
        this.varName = "lastPage";

        if (!sessionStorage.getItem(this.varName)) {
            sessionStorage.setItem(this.varName, this.defaultPageId);
        }
    }

    set(pageId) {
        sessionStorage.setItem(this.varName, pageId);
    }

    get() {
        return sessionStorage.getItem(this.varName);
    }
}

globalThis.LastPage = LastPage;
