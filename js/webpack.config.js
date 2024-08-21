var webpack = require('webpack');
var UglifyJsPlugin = require('uglifyjs-webpack-plugin');
module.exports = {
  entry: [
    'whatwg-fetch',
    './node-subscribe-form.js'
  ],
  output: {
    path: __dirname,
    filename: 'node-subscribe-form.bundle.js'
  },
  module: {
    loaders: [
      {test: /\.js$/,
        exclude: /node_modules/,
        loader: 'babel-loader'
      },
      {test: /\.css$/,
        loader: 'style-loader!css-loader'
      }
    ]
  },
  plugins: [
    new UglifyJsPlugin(),
    new webpack.DefinePlugin({
      'process.env': {
        NODE_ENV: JSON.stringify('production')
      }
    })
  ]
};
