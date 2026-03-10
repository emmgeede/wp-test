
window.addEventListener('DOMContentLoaded', function() {
    const tables = document.getElementsByClassName("acpt-table-with-pagination");

    /**
     *
     * @param array
     * @param pageSize
     * @param pageNumber
     * @return {*}
     */
    const paginate = (array, pageSize, pageNumber) => {
        return array.slice((pageNumber - 1) * pageSize, pageNumber * pageSize);
    };

    /**
     * Populate the table
     *
     * @param tbody
     * @param page
     * @param perPage
     * @param values
     */
    const populateTable = (tbody, page, perPage, values) => {

        tbody.innerHTML = "";
        const rows = paginate(values, perPage, page);

        rows.map((row, tIndex) => {

            const r = document.createElement("tr");
            r.dataset.rowId = parseInt(tIndex)+1;

            row.map((cell, cIndex) => {

                console.log(
                    cell.style
                );

                const td = document.createElement(cell.tag);
                td.innerHTML = cell.value;
                td.style.cssText = cell.style;
                td.dataset.rowId = parseInt(tIndex)+1;
                td.dataset.colId = parseInt(cIndex)+1;
                r.append(td);
            });

            tbody.append(r);
        });

        const evt = new Event("acpt_table_populated");
        document.dispatchEvent(evt);
    };

    if(tables.length > 0) {
        for (let i = 0; i < tables.length; i++) {

            const table = tables.item(i);
            const perPage = table.dataset.pagination;
            const values = JSON.parse(table.dataset.value);
            const pageNumbers = Math.ceil(parseInt(values.length)/parseInt(perPage));
            const tbody = table.getElementsByTagName("tbody")[0];

            // append pagination links
            const links = document.createElement("ul");
            links.classList.add("acpt-table-pagination");

            for (let k = 0; k < pageNumbers; k++) {
                const link = document.createElement("li");
                const a = document.createElement("a");

                a.href= `#`;
                a.classList.add("acpt-table-pagination-link");

                if(k === 0){
                    a.classList.add("current");
                }

                a.dataset.page = k+1;
                a.innerHTML = k+1;

                // change page
                a.addEventListener("click", function (e) {
                    e.preventDefault();

                    const previouslyCreatedLinks = document.getElementsByClassName("acpt-table-pagination-link");

                    for (let i = 0; i < previouslyCreatedLinks.length; i++) {
                        const l = previouslyCreatedLinks.item(i);
                        l.classList.remove("current");
                    };

                    a.classList.add("current");
                    populateTable(tbody, (k+1), perPage, values);
                });

                link.append(a);
                links.append(link);
            }

            table.parentNode.insertBefore(links, table.nextSibling);

            // populate tbody
            populateTable(tbody, 1, perPage, values);
        }
    }
});