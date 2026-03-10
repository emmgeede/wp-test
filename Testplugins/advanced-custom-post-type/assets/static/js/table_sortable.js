
window.onload = function () {

    const initTableSortable = () => {
        const tables = document.getElementsByClassName("acpt-table-sortable");

        /**
         *
         * @param tbody
         * @param values
         */
        const populateTable = (tbody, values) => {

            const trs = tbody.getElementsByTagName("tr");

            for (let k = 0; k < trs.length; k++) {
                const tr = trs.item(k);
                const tds = tr.getElementsByTagName("td");

                for (let c = 0; c < tds.length; c++) {
                    const td = tds.item(c);

                    if(values[k] && values[k][c]){
                        td.innerHTML = values[k][c];
                    }
                }
            }
        };

        if(tables.length > 0) {
            for (let i = 0; i < tables.length; i++) {
                const table = tables.item(i);
                const tbody = table.getElementsByTagName("tbody")[0];
                const ths = table.getElementsByTagName("th");
                const trs = tbody.getElementsByTagName("tr");

                let values = [];

                for (let k = 0; k < trs.length; k++) {
                    const tr = trs.item(k);
                    const tds = tr.getElementsByTagName("td");

                    let trValues = [];

                    for (let c = 0; c < tds.length; c++) {
                        const td = tds.item(c);
                        trValues.push(td.innerHTML);
                    }

                    values.push(trValues);
                }

                for (let i = 0; i < ths.length; i++) {
                    const th = ths.item(i);
                    const rowNumber = th.dataset.rowId;
                    const colNumber = th.dataset.colId;

                    th.addEventListener("click", function(e){
                        e.preventDefault();

                        const sorting = th.classList.contains("desc") ? "desc" : "asc";

                        for (let y = 0; y < ths.length; y++) {
                            const h = ths.item(y);
                            h.classList.remove("asc");
                            h.classList.remove("desc");
                        }

                        if(sorting === "asc"){
                            th.classList.remove("asc");
                            th.classList.add("desc");
                        } else {
                            th.classList.remove("desc");
                            th.classList.add("asc");
                        }

                        populateTable(
                            tbody,
                            values.sort((a, b) => {

                                function isNumeric(num){
                                    return !isNaN(num)
                                }

                                const valA = isNumeric(a[colNumber]) ? Number(a[colNumber]) : a[colNumber];
                                const valB = isNumeric(b[colNumber]) ? Number(b[colNumber]) : b[colNumber];

                                // DESC
                                if(sorting === "desc"){
                                    return (valA > valB) ? -1 : 1;
                                }

                                // ASC
                                return (valA < valB) ? -1 : 1;
                            })
                        );
                    });
                }
            }
        }
    };

    initTableSortable();

    document.addEventListener("acpt_table_populated", function(e){
        initTableSortable();
    });
};