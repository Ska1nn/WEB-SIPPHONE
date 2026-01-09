class LastPage {
    constructor() {
        this.defaultPageId = "status";
        this.varName = "lastPage";

        if (!sessionStorage.getItem(this.varName))
            sessionStorage.setItem(this.varName, this.defaultPageId);
    }

    /** Store last page ID
     *
     * @param {string} pageId 
     */
    set(pageId) {
        sessionStorage.setItem(this.varName, pageId);
    }

    /** Get last page ID
     *
     * @returns {string} pageId
     */
    get() {
        return sessionStorage.getItem(this.varName);
    }
};

module.exports = LastPage;
