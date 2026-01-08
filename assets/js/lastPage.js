class LastPage {
    constructor() {
        this.defaultPageId = "status";
        this.varName = "lastPage";

        if (!localStorage.getItem(this.varName))
            localStorage.setItem(this.varName, this.defaultPageId);
    }

    /** Store last page ID
     *
     * @param {string} pageId 
     */
    set(pageId) {
        localStorage.setItem(this.varName, pageId);
    }

    /** Get last page ID
     *
     * @returns {string} pageId
     */
    get() {
        return localStorage.getItem(this.varName);
    }
};

module.exports = LastPage;
