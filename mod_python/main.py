from flask import Flask, jsonify, request
from flask_wallet_rpc import Walletrpc, wallet

app = Flask(__name__)
app.config.from_object('settings')
rpc_call = Walletrpc()
rpc_call.init_app(app)

@app.route('/', methods=['GET'])
def index():
    # also add route for /help
    return jsonify(response), 200


@app.route('/test', methods=['GET'])
def test():
    # GET /test/*
    return jsonify(response), 200


@app.route('/validateaddress/<address>', methods=['GET'])
def validateaddress(address):
    validate = wallet.validateaddress(address)
    if validate['isvalid'] == True:
        response = wallet.getaddressbalance(address)
        return jsonify(response['balance']), 200
    else:
        return jsonify(validate), 200


@app.route('/getbalance/<address>', methods=['GET'])
def getbalance(address):
    response = wallet.getaddressbalance(address)
    return jsonify(response), 200

@app.route('/listtransactions/address', methods=['GET'])
def listtransactions():
    # GET /listtransactions/:address/:height
    # GET /listtransactions/:address/:height/:numblocks
    return jsonify(response), 200


@app.route('/createmirtransaction/address/amount/txid/offset', methods=['GET'])
def createmirtransaction():

    return jsonify(response), 200


@app.route('/listmirrtransactions/address', methods=['GET'])
def listmirrtransactions():
    # GET /listmirrtransactions/:address/:height
    # GET /listmirrtransactions/:address/:height/:numblocks
    return jsonify(response), 200


@app.route('/listallmirrtransactions', methods=['GET'])
def listallmirrtransactions():
    # GET /listallmirrtransactions/:height
    # GET /listallmirrtransactions/:height/:numblocks
    return jsonify(response), 200


@app.route('/blockcount', methods=['GET'])
def blockcount():
    response = wallet.getblockcount()
    return jsonify(response), 200


@app.route('/getblockhash/height', methods=['GET'])
def getblockhash():
    # Return block hash at the specified height
    return jsonify(response), 200


@app.route('/getblockhashes/height/limit', methods=['GET'])
def getblockhashes():
    # Return block hash at the specified height
    return jsonify(response), 200


@app.route('/getblock/hex', methods=['GET'])
def getblock():
    # GET /getblock/:height/:numblocks
    return jsonify(response), 200


@app.route('/getevents/height/numblocks', methods=['GET'])
def getevents():

    return jsonify(response), 200


@app.route('/decoderawtransaction/hex', methods=['GET'])
def decoderawtransaction():

    return jsonify(response), 200


@app.route('/sendrawtransaction/hex', methods=['GET'])
def sendrawtransaction():
    # Return transaction id (hex)
    return jsonify(response), 200



if __name__ == '__main__':
    app.run(host='0.0.0.0', use_reloader=False, debug=True)
