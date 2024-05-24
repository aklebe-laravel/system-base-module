export default class CliConsole {

    consoleClassName = 'default-console';
    elConsole = null;

    constructor(consoleClassName) {
        let thisInstance = this;
        thisInstance.consoleClassName = consoleClassName;
    }

    /**
     *
     * @param str
     * @param is_xhtml
     * @returns {string}
     */
    nl2br = function (str, is_xhtml) {
        if (typeof str === 'undefined' || str === null) {
            return '';
        }
        var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
        return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
    }

    /**
     *
     * @param msg
     * @param timestamp
     */
    createConsoleRow = function (msg, timestamp) {

        let thisInstance = this;
        let eTimestamp = document.createElement("span")
        eTimestamp.classList.add("timestamp");
        eTimestamp.innerHTML += timestamp;
        let eMessage = document.createElement("div")
        eMessage.classList.add("message");
        eMessage.appendChild(eTimestamp);
        // eMessage.innerHTML += ' ' + thisInstance.nl2br(msg);
        eMessage.innerHTML += ' ' + msg;
        thisInstance.elConsole.appendChild(eMessage);

    }

    /**
     *
     * @param msg
     * @param timestamp
     */
    addBroadcastMessage = function (msg, timestamp) {

        let thisInstance = this;
        if (typeof timestamp === 'undefined') {
            timestamp = '-';
        }

        let msgRows = msg.split("\n");
        for (let m in msgRows) {
            thisInstance.createConsoleRow(msgRows[m], timestamp);
        }
    }

    /**
     *
     */
    start = function () {

        let thisInstance = this;
        thisInstance.elConsole = document.getElementsByClassName(thisInstance.consoleClassName)[0];

        // initial text
        thisInstance.addBroadcastMessage('Console ready.')
        thisInstance.addBroadcastMessage('You should see broadcast events here while deployments running.')

        // let channel = 'deployments.2';
        let channel = appData.default_console_channel; // 'deployments.default-console'
        // console.log("Broadcast channel: " + channel)
        Echo.private(channel).listen('.deployment.running', e => {

            // add row to console
            thisInstance.addBroadcastMessage(e.processContainer.message, e.processContainer.timestamp)

            // autoscroll to bottom
            thisInstance.elConsole.scrollTop = thisInstance.elConsole.scrollHeight;

        })
    }

} // no ; at the end of class declaration