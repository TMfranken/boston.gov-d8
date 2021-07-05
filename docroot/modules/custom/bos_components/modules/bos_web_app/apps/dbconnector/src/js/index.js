
const listen_port = require('../common/env.config.js').port;

const express = require('express');
// const redis   = require("redis");

// const session = require('express-session');
// var redisStore = require('connect-redis')(session);
// var client  = redis.createClient();
const app = express();
const bodyParser = require('body-parser');

// Temp session caching - replace with redis
// var cookieParser = require('cookie-parser');
// app.use(cookieParser());
// app.use(session({
//   secret: "Shh, its a secret!",
//   saveUninitialized: false,
//   resave: false}));

// Include the models routing files.
const AuthorizationRouter = require('../models/authorization/routes.config');
const UsersRouter = require('../models/users/routes.config');
const ConnectionsRouter = require('../models/connections/routes.config');

app.use(function (req, res, next) {
  res.set('Cache-Control', 'no-store');
  res.header('Access-Control-Allow-Origin', '*');
  res.header('Access-Control-Allow-Credentials', 'true');
  res.header('Access-Control-Allow-Methods', 'GET,HEAD,PUT,PATCH,POST,DELETE');
  res.header('Access-Control-Expose-Headers', 'Content-Length');
  res.header('Access-Control-Allow-Headers', 'Accept, Authorization, Content-Type, X-Requested-With, Range');
  if (req.method === 'OPTIONS') {
    return res.sendStatus(200);
  } else {
    return next();
  }
});

app.use(bodyParser.urlencoded({ extended: false }));
app.use(bodyParser.json());

app.set('trust proxy', true)

// Create the endpoints from the models routing files.
AuthorizationRouter.routesConfig(app);
UsersRouter.routesConfig(app);
ConnectionsRouter.routesConfig(app);

// Start the express server service.
app.listen(listen_port, function () {
  console.log('app listening at port %s', listen_port);
});