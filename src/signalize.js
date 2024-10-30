const Signalize = window.Signalize || {};

// Constructor function voor de SymbolTable
Signalize.SymbolTable = function(symbols) {
    // Initialiseer de symbols eigenschap als een lege object
    this.symbols = {};

    // Declareer elke symbol in de meegegeven symbols object
    for (const name in symbols) {
        if (symbols.hasOwnProperty(name)) {
            this.declare(name, symbols[name]);
        }
    }
};

// Declareer een nieuwe symbol met een naam en type
Signalize.SymbolTable.prototype.declare = function(name, type) {
    this.symbols[name] = {
        type: type, // Type van de symbol
        value: ''   // Initiële waarde van de symbol
    };
};

// Controleer of een symbol bestaat met de gegeven naam
Signalize.SymbolTable.prototype.has = function(name) {
    return this.symbols.hasOwnProperty(name);
};

// Haal de waarde op van de symbol met de gegeven naam
Signalize.SymbolTable.prototype.get = function(name) {
    return this.has(name) ? this.symbols[name].value : null;
};

// Stel de waarde in van een bestaande symbol met de gegeven naam
Signalize.SymbolTable.prototype.set = function(name, value) {
    if (this.has(name)) {
        this.symbols[name].value = value;
    }
};

// Haal het type op van de symbol met de gegeven naam
Signalize.SymbolTable.prototype.getType = function(name) {
    return this.has(name) ? this.symbols[name].type : null;
};

Signalize.Executer = function(bytecode) {
    this.pos = 0;
    this.bytecode = bytecode.split("||");
    this.symbolTableStack = [];
};

Signalize.Executer.prototype.getVariable = function(name) {
    for (let i = this.symbolTableStack.length - 1; i >= 0; --i) {
        if (this.symbolTableStack[i].has(name)) {
            return this.symbolTableStack[i].get(name);
        }
    }

    return null;
};

Signalize.Executer.prototype.setVariable = function(name, value) {
    for (let i = this.symbolTableStack.length - 1; i >= 0; --i) {
        if (this.symbolTableStack[i].has(name)) {
            this.symbolTableStack[i].set(name, value);
            return;
        }
    }
};

Signalize.Executer.prototype.handleTokenStream = function() {
    const exploded = this.bytecode[this.pos++].split("##");
    const numberOfTokens = parseInt(exploded[1]);
    const lastToken = this.pos + numberOfTokens;
    const localVariables = JSON.parse(exploded[2]);

    this.symbolTableStack.push(new Signalize.SymbolTable(localVariables));

    while (this.pos < lastToken) {
        this.executeByteCode();
    }

    this.symbolTableStack.pop();
};

/**
 * Handle internal function call
 * @param functionName
 * @param numberOfParameters
 * @returns {string|*|number|boolean|string}
 */
Signalize.Executer.prototype.handleFunctionCall = function(functionName, numberOfParameters) {
    const parameters = [];
    for (let i = 0; i < numberOfParameters; ++i) {
        parameters.push(this.executeByteCode());
    }

    switch (functionName) {
        case 'StrToInt':
            if (/^([-+])?\d+$/.test(parameters[0]) === false) {
                throw new Error('Runtime error: Conversie van string naar int mislukt');
            }

            return parseInt(parameters[0], 10);

        case 'IntToFloat':
            return parseFloat(parameters[0]);

        case 'StrToFloat':
            let strToFloatValue = parameters[0];
            let intRegExp = /^([-+])?\d+$/;
            let floatRegExp = /^([-+])?([0-9]+(\.[0-9]+)?|\.[0-9]+)$/;

            if ((intRegExp.test(strToFloatValue) === false) && (floatRegExp.test(strToFloatValue) === false)) {
                throw new Error('Runtime error: Conversie van string naar float mislukt');
            }

            return parseFloat(parameters[0]);

        case 'Write':
            console.log(parameters[0]);
            return;

        case 'WriteLn':
            console.log(parameters[0] + "\n");
            return;

        case 'Uppercase':
            return parameters[0].toUpperCase();

        case 'Lowercase':
            return parameters[0].toLowerCase();

        case 'Pos':
            return parameters[0].indexOf(parameters[1], parameters[2]);

        case 'Copy':
            return parameters[0].substring(parameters[1], parameters[1] + parameters[2]);

        case 'FloatToInt':
            return parseInt(parameters[0]);

        case 'Round':
            // Haal het getal af van zijn vloerwaarde.
            const number = parameters[0];
            const absNumber = Math.abs(number);
            const fraction = absNumber - Math.floor(absNumber);

            // Controleer of de fractie groter dan of gelijk aan 0.5 is.
            if (fraction >= 0.5) {
                return number >= 0 ? Math.floor(number) + 1 : Math.ceil(number) + 1;
            } else {
                return number >= 0 ? Math.floor(number) : Math.ceil(number);
            }

        case 'Floor':
            return Math.floor(parameters[0]);

        case 'Ceil':
            return Math.ceil(parameters[0]);

        case 'Frac':
            return parameters[0] - Math.floor(parameters[0]);

        case 'IntToStr':
        case 'FloatToStr':
            return parameters[0].toString();

        case 'BoolToStr':
            return parameters[0] ? "true" : "false";

        case 'StrToBool':
            return ["true", "1"].includes(parameters[0]);

        case 'Random':
            return Math.floor(Math.random() * parameters[0]);

        case 'Length':
            return parameters[0].length;

        case 'StrReplace':
            return parameters[2].split(parameters[0]).join(parameters[1]);

        case 'IsNumeric':
            return !isNaN(parameters[0]);

        case 'IsInteger':
            return /^-?\d+$/.test(parameters[0]);

        case 'IsBool':
            return ["true", "false", "1", "0"].includes(parameters[0].toLowerCase());

        case 'Concat':
            return parameters.join('');

        case 'Chr':
            return String.fromCharCode(parameters[0]);

        case 'Ord':
            return parameters[0].charCodeAt(0);

        case 'Odd':
            return Math.round(parameters[0]) % 2 !== 0;

        case 'Inc':
            let variableValueForInc = this.getVariable(parameters[0]);
            this.setVariable(parameters[0], variableValueForInc + 1);
            return null;

        case 'Dec':
            let variableValueForDec = this.getVariable(parameters[0]);
            this.setVariable(parameters[0], variableValueForDec - 1);
            return null;

        case 'GetSelectedOptionId' :
            const gsovContainer = parameters[0];
            const gsovKey = parameters[1];
            const gsovElement = document.querySelector(`[data-container="${gsovContainer}"][data-key="${gsovKey}"]`);
            let gsovSelectedElement = gsovElement.getAttribute('data-option-selected');
            let gsovValue = '';

            if (gsovSelectedElement !== null) {
                if (typeof gsovSelectedElement == 'string') {
                    gsovSelectedElement = JSON.parse(gsovSelectedElement);
                }

                if (gsovSelectedElement != null) {
                    gsovValue = gsovSelectedElement.id;
                }
            }

            return String(gsovValue)

        case 'GetSelectedOptionExtraValue' :
            const gsoveContainer = parameters[0];
            const gsoveKey = parameters[1];
            const gsoveIndex =  parameters[2];
            const gsoveElement = document.querySelector(`[data-container="${gsoveContainer}"][data-key="${gsoveKey}"]`);
            let gsoveSelectedElement = gsoveElement.getAttribute('data-option-selected');
            let gsoveValue = '';

            if (typeof gsoveSelectedElement == 'string') {
                gsoveSelectedElement = JSON.parse(gsoveSelectedElement);
            }

            if (gsoveSelectedElement != null) {
                gsoveValue = gsoveSelectedElement.options[gsoveIndex] ?? '';
            }

            return String(gsoveValue)

        case 'SetValue' :
            postal.publish({
                topic: 'configbuilder.item.set',
                data: {
                    container: parameters[0],
                    key: parameters[1],
                    value: parameters[2],
                }
            });

            return null;

        default:
            throw new Error("Unknown function: " + functionName);
    }
};

Signalize.Executer.prototype.executeByteCode = function(event) {
    let variableName;
    const currentCode = this.bytecode[this.pos];

    // Bindings
    if (["visible", "enable", "click", "value", "options"].includes(event)) {
        return this.executeByteCode();
    }

    // CSS and Style bindings
    if (["css", "style"].includes(event)) {
        // Verhoog de positie en splits de bytecode om de optie namen te verkrijgen.
        const optionNames = JSON.parse(this.bytecode[this.pos++]);

        // Maak een object van resultaten door de bijbehorende bytecodes uit te voeren.
        const result = {};
        optionNames.forEach(name => {
            result[name] = this.executeByteCode();
        });

        // Retourneer het resultaat
        return result;
    }

    // Logical expression
    if (["+", "-", "*", "/", "==", "!=", "<", ">", "<=", ">=", "and", "or"].includes(currentCode)) {
        ++this.pos;
        const valueA = this.executeByteCode();
        const valueB = this.executeByteCode();

        switch (currentCode) {
            case "+":
                return valueA + valueB;

            case "-":
                return valueA - valueB;

            case "*":
                return valueA * valueB;

            case "/":
                return valueA / valueB;

            case "==":
                return valueA === valueB;

            case "!=":
                return valueA !== valueB;

            case "<":
                return valueA < valueB;

            case ">":
                return valueA > valueB;

            case "<=":
                return valueA <= valueB;

            case ">=":
                return valueA >= valueB;

            case "and":
                return valueA && valueB;

            case "or":
                return valueA || valueB;
        }
    }

    // Keyword 'true' indicates boolean value true
    if (currentCode === "true") {
        ++this.pos;
        return true;
    }

    // Keyword 'false' indicates boolean value false
    if (currentCode === "false") {
        ++this.pos;
        return false;
    }

    // Number (float or int)
    if (currentCode.startsWith("n:")) {
        const number = this.bytecode[this.pos++].substring(2);
        return number.indexOf('.') !== -1 ? parseFloat(number) : parseInt(number);
    }

    // String
    if (currentCode.startsWith("s:")) {
        return this.bytecode[this.pos++].substring(2);
    }

    // Variable assignment
    if (currentCode.startsWith("=")) {
        variableName = this.bytecode[this.pos++].substring(1);
        this.setVariable(variableName, this.executeByteCode());
        return null;
    }

    // Variable
    if (currentCode.startsWith("id:")) {
        variableName = this.bytecode[this.pos++].substring(3);
        return this.getVariable(variableName);
    }

    // Reference to variable
    if (currentCode.startsWith("r_id:")) {
        return this.bytecode[this.pos++].substring(5);
    }

    // Token stream
    if (currentCode.startsWith("ts##")) {
        this.handleTokenStream();
        return null;
    }

    // If statement
    if (currentCode.startsWith("if:")) {
        const jmpWhenFalse = this.bytecode[this.pos++].substring(3);

        if (!this.executeByteCode()) {
            this.pos = parseInt(jmpWhenFalse, 10);
        }

        return null;
    }

    // Function call
    if (currentCode.startsWith("fc:")) {
        const exploded = this.bytecode[this.pos++].split(":");
        return this.handleFunctionCall(exploded[1], parseInt(exploded[2], 10));
    }

    // Jump to bytecode
    if (currentCode.startsWith("jmp:")) {
        this.pos = parseInt(this.bytecode[this.pos++].substring(4), 10);
        return null;
    }

    // Negate
    if (currentCode === 'negate') {
        ++this.pos;
        const valueToNegate = this.executeByteCode();
        return !valueToNegate;
    }

    // Bind fetch
    if (currentCode.startsWith("@")) {
        ++this.pos;

        const delimiterIndex = currentCode.indexOf(".");
        const isDotPresent = delimiterIndex !== -1;
        const bindContainer = isDotPresent ? currentCode.substring(1, delimiterIndex) : null;
        const bindKey = isDotPresent ? currentCode.substring(delimiterIndex + 1) : null;
        const selector = isDotPresent
            ? `[data-container="${bindContainer}"][data-key="${bindKey}"]`
            : `span[data-type="configbuilder-monitor"][data-key="config.${currentCode.substring(1)}"]`;

        const bindElement = document.querySelector(selector);

        if (bindElement === null) {
            return "";
        } else if (isDotPresent) {
            return String(bindElement.value);
        } else {
            return String(bindElement.getAttribute('data-value'));
        }
    }

    ++this.pos;
    return null;
};